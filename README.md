# BBot bot engine

### Introduction

BBot is a conversational bot engine created by Botanic/SEED team that uses .Bot specification to define a conversational bot and .Flow standard to determine the flow of the conversation.

Part of the SEED token project. This is a sneak preview - there is more to come.
See [the Wiki](https://github.com/SeedVault/SEEDtoken-IP/wiki) for more information.


## Version
This PHP engine is a proof of concept of Flow 1.0 and Flow 2.0. The current repository works with .Flow 1.0. A newer version will be released soon. 

## Disclaimer

These files are made available to you on an as-is and restricted basis, and may only be redistributed or sold to any third party as expressly indicated in the Terms of Use for Seed Vault.


### About the SEED Token Project
SEED democratizes AI by offering an open and independent alternative to the monopolies of a few large corporations that currently control conversational user interfaces (CUIs) and AI technologies. SEED's licensed, monetized open-source platform for bots on blockchain supports collaboration and creative compensation that will exceed the proprietary deployments from industry giants. We are also giving users back control of their personal data. Find out more about the SEED Token project at [seedtoken.io](https://seedtoken.io). See the Connect section at the end for contact info.

### Documentation
- [.Flow standard](https://github.com/SeedVault/flow) to know more about the standard used to create the conversation dialogs.
- [.Bot description](https://github.com/SeedVault/bot) to see the format of the configuration file used by BBOT to create bots.


# Build Development Setup 

## Backend PHP code

Create backend/.env file (it's excluded from the git repo). Use .env-example as source.\
make sure APP_ENV=local

```bash
# Docroot is at backend/public. You can make it work just by running cli php (or your webserver of choice)
# Make sure to listen to port 8000 in dev instance as frontend webpack will send api requests to it
apt-get update && apt-get install php7.0 php7.0-curl php7.0-json php7.0-mbstring php7.0-mcrypt php7.0-mysql php-apcu 
cd backend
composer install
vim .env
php -S localhost:8000 -t public
```


## Frontend 

Frontend is bundled with Webpack. It has it's own webserver to provide features like Hot-loading.
This builds and run the application as a development instance and sets the test Hadron web channel's URI

```bash
# To make webpack build dev files and start webpack webserver. 
# This will start listening by default to http://localhost:8080
cd frontend
npm ci
BOTANIC_ENV=local HADRON_URI="https://domain/dev_author/hadron.php" npm run dev
```

### Connect
Feel free to throw general questions regarding SEED and what to expect in the following months here on GitHub (or GitLab) at  @consiliera (gaby@seedtoken.io) :sunny: 

**Connect with us elsewhere** 
- [Follow us on Twitter](https://twitter.com/SEED_token)
- Always here the latest news first on [Telegram](https://t.me/seedtoken) and [Discord](https://discord.gg/Suv5bFT)

Seed Vault Code (c) Botanic Technologies, Inc. Used under license.

