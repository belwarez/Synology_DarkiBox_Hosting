<?php
/*
    DarkiBoxCom host for Synology Download Station
    Version : 0.1
    Description : Use Darkibox API with API key, using available direct link.
*/

class DarkiBoxComHosting
{
    private $Url;
    private $ApiKey;
    private $HostInfo;
    private $FileName = '';
    private $LogEnabled = false;
    private $LogFile = '/tmp/darkibox_com.log';

    /**
     * Constructor
     *
     * @param string $url       URL donnée par Download Station
     * @param string $username  Champ "nom d'utilisateur" (utilisé ici pour les options, ex: local_log=1)
     * @param string $password  Champ "mot de passe" = API key Darkibox
     * @param array  $hostInfo  Infos fournies par Download Station
     */
    public function __construct($url, $username, $password, $hostInfo)
    {
        $this->Url      = $url;
        $this->ApiKey   = trim($password);   // le password contient la clé API
        $this->HostInfo = $hostInfo;

        // Le username sert uniquement de zone de configuration (ex: "local_log=1")
        $this->parseConfig($username);
        $this->log("Construct : url={$this->Url} | hostInfo=" . json_encode($this->HostInfo));
    }

    /**
     * Vérification des identifiants (appelée par Download Station).
     * On vérifie simplement que la clé API est valide via /api/account/info.
     */
    public function Verify($ClearCookie)
    {
        $this->log("Verify : Start");
        $result = $this->Login();
        $this->log("Verify : End | result={$result}");
        return $result;
    }

    /**
     * Récupération des informations de téléchargement.
     * - vérifie l'API key
     * - extrait le file_code de l'URL
     * - récupère les métadonnées du fichier (nom)
     * - récupère un lien direct via /api/file/direct_link
     * - retourne à Download Station : nom + URL.
     */
    public function GetDownloadInfo()
    {
        $this->log("GetDownloadInfo : Start | url={$this->Url}");

        if (empty($this->ApiKey)) {
            $this->log("GetDownloadInfo : No API key provided (password empty)");
            return array(DOWNLOAD_ERROR => ERR_FILE_NO_EXIST);
        }

        // Vérification de la clé API
        $loginResult = $this->Login();
        if ($loginResult != USER_IS_PREMIUM) {
            $this->log("GetDownloadInfo : Login / API key invalid");
            return array(DOWNLOAD_ERROR => ERR_FILE_NO_EXIST);
        }

        // Extraction du file_code à partir de l'URL
        $fileCode = $this->extractFileCode($this->Url);
        if ($fileCode === false) {
            $this->log("GetDownloadInfo : Unable to extract file code from URL");
            return array(DOWNLOAD_ERROR => ERR_FILE_NO_EXIST);
        }
        $this->log("GetDownloadInfo : fileCode={$fileCode}");

        // Nom de fichier par défaut = file_code
        $this->FileName = $fileCode;

        // Récupération des infos fichier pour avoir un nom propre
        $info = $this->apiRequest('file/info', array(
            'key'       => $this->ApiKey,
            'file_code' => $fileCode
        ));

        if ($info &&
            isset($info['status']) &&
            (int)$info['status'] === 200 &&
            isset($info['result'][0]['file_title']) &&
            $info['result'][0]['file_title'] !== '') {

            $this->FileName = $info['result'][0]['file_title'];
        }
        $this->log("GetDownloadInfo : FileName resolved to {$this->FileName}");

        // Récupération du lien direct (sans q/hls → on laisse l'API décider des versions)
        $direct = $this->apiRequest('file/direct_link', array(
            'key'       => $this->ApiKey,
            'file_code' => $fileCode
        ));

        if (!$direct || !isset($direct['status']) || (int)$direct['status'] !== 200) {
            $this->log("GetDownloadInfo : direct_link API returned error");
            return array(DOWNLOAD_ERROR => ERR_FILE_NO_EXIST);
        }

        // Sélection d'une URL exploitable dans la réponse
        $downloadUrl = $this->selectBestDownloadUrl($direct);
        if ($downloadUrl === false) {
            $this->log("GetDownloadInfo : No usable download URL found in response");
            return array(DOWNLOAD_ERROR => ERR_FILE_NO_EXIST);
        }

        $this->log("GetDownloadInfo : End | download url={$downloadUrl}");

        return array(
            DOWNLOAD_FILENAME => $this->FileName,
            DOWNLOAD_URL      => $downloadUrl
        );
    }

    /**
     * "Login" : vérifie simplement que la clé API est valide via /api/account/info.
     */
    private function Login()
    {
        if (empty($this->ApiKey)) {
            $this->log("Login : No API key (password empty)");
            return LOGIN_FAIL;
        }

        $res = $this->apiRequest('account/info', array(
            'key' => $this->ApiKey
        ));

        if ($res && isset($res['status']) && (int)$res['status'] === 200) {
            $this->log("Login : OK (account/info status=200)");
            return USER_IS_PREMIUM;
        }

        $this->log("Login : Failed (invalid API key or API error)");
        return LOGIN_FAIL;
    }

    /**
     * Appel générique à l'API Darkibox :
     *   https://darkibox.com/api/{path}?{params}
     */
    private function apiRequest($path, $params)
    {
        $url = 'https://darkibox.com/api/' . $path . '?' . http_build_query($params);
        $this->log("API Request : {$url}");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        // Compatibilité maximale (certificats parfois capricieux)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, 'DownloadStation');

        $response = curl_exec($ch);
        if ($response === false) {
            $this->log('API Request : curl error=' . curl_error($ch));
            curl_close($ch);
            return false;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->log("API Request : HTTP {$httpCode} response={$response}");

        $json = json_decode($response, true);
        if (!is_array($json)) {
            $this->log("API Request : Invalid JSON response");
            return false;
        }

        return $json;
    }

    /**
     * Extraction du file_code à partir de l'URL :
     *   - https://darkibox.com/abcd1234
     *   - https://darkibox.com/abcd1234.html
     *   - https://www.darkibox.com/abcd1234
     */
    private function extractFileCode($url)
    {
        $this->log("extractFileCode : from url={$url}");

        $pattern = '#https?://(?:www\\.)?darkibox\\.com/([a-zA-Z0-9]+)(?:\\.html)?#';
        if (preg_match($pattern, $url, $matches)) {
            $code = $matches[1];
            $this->log("extractFileCode : found code={$code}");
            return $code;
        }

        $this->log("extractFileCode : no match");
        return false;
    }

    /**
     * Sélectionne la meilleure URL de téléchargement à partir de la réponse direct_link.
     *
     * 1) Si "versions" existe :
     *      - tente de trouver name = "o" (original)
     *      - sinon prend la première entrée.
     * 2) Si pas de "versions" mais un champ "url" direct dans result :
     *      - utilise result["url"].
     */
    private function selectBestDownloadUrl($directResponse)
    {
        // Cas 1 : tableau "versions"
        if (isset($directResponse['result']) &&
            isset($directResponse['result']['versions']) &&
            is_array($directResponse['result']['versions'])) {

            $versions    = $directResponse['result']['versions'];
            $originalUrl = false;

            // D'abord, chercher name == "o" (original)
            foreach ($versions as $v) {
                if (isset($v['name']) && $v['name'] === 'o' && !empty($v['url'])) {
                    $originalUrl = $v['url'];
                    break;
                }
            }

            // Sinon, fallback sur la première version disponible
            if ($originalUrl === false &&
                isset($versions[0]['url']) &&
                $versions[0]['url'] !== '') {

                $originalUrl = $versions[0]['url'];
            }

            return $originalUrl;
        }

        // Cas 2 : pas de "versions", mais un champ "url" direct
        if (isset($directResponse['result']) &&
            isset($directResponse['result']['url']) &&
            $directResponse['result']['url'] !== '') {

            return $directResponse['result']['url'];
        }

        // Rien d'exploitable
        return false;
    }

    /**
     * Parse les options à partir du "username".
     *
     * Format attendu :
     *   cle1=valeur1;cle2=valeur2;...
     *
     * Exemple :
     *   username = "local_log=1"
     *
     * Tout ce qui n'est pas sous forme cle=valeur est ignoré
     * (ex : "cfg" sera ignoré).
     */
    private function parseConfig($username)
    {
        $username = trim($username);
        if ($username === '') {
            // Pas d'options : on ne logge rien, pas d'erreur.
            return;
        }

        $pairs = explode(';', $username);
        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }

            $tmp = explode('=', $pair, 2);
            if (count($tmp) != 2) {
                // Mot non conforme (pas cle=valeur) → ignoré
                continue;
            }

            $key   = trim($tmp[0]);
            $value = trim($tmp[1]);

            if ($key === 'local_log' && $value === '1') {
                $this->LogEnabled = true;
            }
        }

        if ($this->LogEnabled) {
            $this->log("parseConfig : local_log enabled");
        }
    }

    /**
     * Logger simple (activé uniquement si local_log=1).
     */
    private function log($message)
    {
        if (!$this->LogEnabled) {
            return;
        }

        $date = date('Y-m-d H:i:s');
        @file_put_contents($this->LogFile, '[' . $date . '] ' . $message . "\n", FILE_APPEND);
    }
}
