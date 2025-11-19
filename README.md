# 📦 DarkiBoxCom — Synology Download Station Host
Version : 0.1 — Téléchargement Darkibox via l'API

### 📥 Téléchargement

👉 [DarkiBoxCom(0.1).host](https://rebrand.ly/darkihost-download)

### 📝 Présentation

DarkiBoxCom(0.1) est un module .host destiné à Synology Download Station permettant de télécharger automatiquement des fichiers depuis Darkibox, en utilisant l’API officielle (Premium).

**⚠️Attention, seuls les comptes premium peuvent télécharger par ce module API, aux dernières nouvelles⚠️**

### 🚀 Installation

Téléchargez le fichier .host
Ouvrez Download Station :
Paramètres → Fichier d’hébergement → Ajouter
Sélectionnez le fichier .host
Sélectionner le service (Modifier/double click) pour paramétrer le compte de téléchargement)

**Parametres du compte**

Dans les paramettres de Download Station, indiquez votre compte afin de vous connecter pour l'hébergeur DarkiBoxCom :

Nom d’utilisateur : api (ou local_log=1 pour activer les logs, voir FAQ)
Mot de passe :	Votre API Key Darkibox

**🔑 Où trouver votre Clé API Darkibox ?**

Dans votre [compte Darkibox](https://darkibox.com/?op=my_account ) :
Menu → API Key
Copiez-collez la clé dans le champ “Mot de passe” du module.

**▶️ Utilisation**

Pour télécharger un fichier, ajoutez simplement un lien Darkibox dans Download Station.


**📝 Logs**

Pour activer les logs :
local_log=1 dans le champ Nom d’utilisateur.


### ❓ FAQ
1. Le module me dit “Utilisateur incorrect”
Deux causes possibles :
Vous avez laissé le champ “Nom d’utilisateur” vide
La clé API est incorrecte / tronquée
Solution :
→ mettre "api" dans “Nom d’utilisateur”
→ vérifier que “Mot de passe” = votre clé API complète

2. J’ai “Fichier(s) non trouvé(s)”

Cela veut dire que l’API /file/direct_link n’a pas fourni de lien direct.
Vérifiez que votre compte est Premium et que le fichier existe bien.

3. Comment faire un rapport de bug ?

Vous pouvez m'envoyer un message dur Discord. 
Pseudo Discord : castorin

### 🧾 Changelog
0.1 – Première version en test.

