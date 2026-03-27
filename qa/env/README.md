# W3TC CI

Allows to test W3TC against multiple environments.
Able to build AWS instances

## Run tests on AWS cluster

```
yum update
yum install -y mc git zip unzip

yum install https://centos7.iuscommunity.org/ius-release.rpm
yum install python36u python36u-pip
pip3.6 install --upgrade awscli

yum install -y ruby

aws configure
```

Copy AWS key w3tcqa to `./key-aws-w3tcqa.pem`

Configure environment
`cp ./000-evnvironment-example ./000-evnvironment`

Add key to ssh agent:
```
. ./000-evnvironment
```

## Vagrant Requirements (functionaly to be restored)
```
sudo apt-get install vagrant
```

Add to `/etc/hosts`:
```
192.168.100.100 wp.sandbox
192.168.100.100 b2.wp.sandbox
192.168.100.100 system.sandbox
```

## How to start vagrant box
- generate virtualboxes
```
./100-generate-envs
```
- check out w3tc plugin and tests
```
./w3tc-clone
```

- go to virtualbox directory of your choice in ./boxes folder
- start virtual box
```
vagrant up
```

- access virtualbox's terminal
```
vagrant ssh
sudo su
```

- access wordpress website

[http://wp.sandbox/](http://wp.sandbox/)

phpMyAdmin:

[http://system.sandbox/phpmyadmin/](http://sandbox-system/phpmyadmin/)
credentials: root / <empty password>

- run some test
in virtualbox's terminal
```
cd ~/w3tcqa
w3test tests/pagecache/a03-check-disk-enhanced.js
```

If folder specified - all tests in that folder will be executed until first failure

## Optional box environment flags

Per-box variables are sourced from `/share/environments/<name>.env` when tests run.

- **`W3D_QA_PHP_OUTPUT_BUFFERING_OFF=1`** — `800-w3tc.sh` runs
  `755-w3tcqa-php-output-buffering.sh`, which installs `.user.ini` and (on Apache) appends
  mod_php `output_buffering` overrides.
- **`W3D_QA_PHP_OUTPUT_BUFFERING_OFF=0`** — the same script **removes** those artifacts
  (only files/blocks it recognizes). Run again with `=1` to reinstall.
- **Unset** — script does nothing (default matrix unchanged).

  On a live box: `export W3D_QA_PHP_OUTPUT_BUFFERING_OFF=1` or `=0` and run
  `/share/scripts/init-box/755-w3tcqa-php-output-buffering.sh` to toggle without editing
  `export.sh`.

## Tools

`---` - does everything. generates boxes, clones code, starts each box and
executes tests inside, builds summary report

`100-generate-envs` - builds AMI and boxes descriptors, doesnt start them

`300-boxes-destroy` - destroys all AWS instances

`w3tc-clone` - clones source code to test and tests.

`800-report-generate` - builds summary report in working/summary.html file

`dev-box-start apache-php73-wp52-single test-box` - creates test machine
