# Watchdog
Simple watchdog to monitor and keep alive a process

## Git Repository
To add watchdog to an existing project execute

#### Add to Project
```
git submodule add git@github.com:blacknell/watchdog.git
git commit -am 'added watchdog submodule'
git push origin master
```

Run composer to install library dependencies.
```$xslt
cd watchdog
composer install
```

#### Incorporate changes from Watchdog project

To pull in upstream changes from watchdog into this project
```
cd watchdog
git fetch
git merge origin/master
```

#### Clone your project

Cloning a project with submodules requires extra steps
```
git clone git@github.com:blacknell/autotweet.git
git submodule init
git submodule update
```

Refer to [Github Books ](https://git-scm.com/book/en/v2/Git-Tools-Submodules) for further information
