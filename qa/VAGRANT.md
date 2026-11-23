# Local Vagrant QA

Run the Puppeteer suite (`qa/tests`) in a single VirtualBox guest that matches one AWS matrix cell. AWS remains the full matrix; this path is for one box on a developer machine.

Generated trees are gitignored: `qa/env/boxes/`, `qa/env/amis/`, `qa/env/working/`. Do not commit them.

## What you get

| Piece | Value |
|---|---|
| Guest OS | Ubuntu (Jammy `ubuntu/jammy64` 20240605.1.0 for PHP 7.4–8.5 cells) |
| Guest IP | `192.168.56.100` (all generated Vagrantfiles share this address) |
| WordPress | `http://wp.sandbox/` — admin user `admin` / password `1` |
| phpMyAdmin | `http://system.sandbox/phpmyadmin/` — `root` / empty password |
| Plugin + tests in guest | `/share/w3tc` ← host `qa/env/working/w3tc` |
| QA scripts in guest | `/share/scripts` ← host `qa/env/scripts` |
| Test runner | `/share/scripts/w3test` from `/root/w3tcqa` (symlink to `/share/w3tc/qa/tests`) |

Only one Vagrant cell can be up at a time unless you change the private IP in that box’s `Vagrantfile`.

## 1. Host packages (new server)

Debian/Ubuntu host with VirtualBox 6.x (the generated boxes use the VirtualBox provider).

```bash
sudo apt-get update
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y \
  vagrant virtualbox dkms linux-headers-$(uname -r) ruby
sudo usermod -aG vboxusers "$USER"
```

Log out and back in so `vboxusers` applies. Confirm the kernel module loaded:

```bash
lsmod | grep vboxdrv
vagrant --version
VBoxManage --version
```

### Nested virtualization

If this machine is already a KVM/QEMU guest, the inner VirtualBox VM needs nested VT-x/AMD-V:

```bash
cat /sys/module/kvm_intel/parameters/nested   # Intel: expect Y
# or
cat /sys/module/kvm_amd/parameters/nested     # AMD
```

The hypervisor that owns this host must allow nested virt. After `100-generate-envs`, add this inside the box `Vagrantfile` `provider :virtualbox` block (generated files are gitignored, so the edit stays local):

```ruby
vb.customize ['modifyvm', :id, '--nested-hw-virt', 'on']
vb.customize ['modifyvm', :id, '--memory', '4096']
vb.customize ['modifyvm', :id, '--cpus', '2']
```

The template ships with 1024 MB RAM, which is too tight for WordPress + Chromium/Puppeteer. Use at least 4096 MB.

### Host `/etc/hosts`

Point sandbox names at the guest IP (not loopback). The old `192.168.100.100` example in `qa/env/README.md` does not match the current `Vagrantfile.erb`.

```text
192.168.56.100 wp.sandbox
192.168.56.100 b2.wp.sandbox
192.168.56.100 for-tests.sandbox
192.168.56.100 for-tests.wp.sandbox
192.168.56.100 system.sandbox
```

Inside the guest, `*.sandbox` is also bound to RFC1918 `10.127.0.1` so CDN Test accepts those hosts.

## 2. Plugin tree and box descriptors

From a clone of this repository:

```bash
cd /path/to/w3-total-cache/qa/env
ruby ./100-generate-envs
```

That rebuilds `amis/` and `boxes/` (every HTTP × PHP × WP × network cell). You only need to `vagrant up` one folder, for example `boxes/apache-php74-wp69-single`.

Ruby is required on the host for this step. You do not need AWS credentials for Vagrant.

### Put the plugin on `/share/w3tc`

The guest synced folder is **`qa/env/working/w3tc`**, not the live checkout.

**Do not** `ln -s` the plugin root (or this clone) to `working/w3tc`. `w3tc-mount.sh` runs `cp -R /share/w3tc` into `wp-content/plugins`. A symlink to the repo makes that copy recurse forever (`qa/env/working` sits inside the tree).

**Option A — copy the checkout you are editing** (typical for local work):

```bash
cd /path/to/w3-total-cache/qa/env
mkdir -p working
rsync -a --delete \
  --exclude '.git/' \
  --exclude '.cursor/' \
  --exclude 'node_modules/' \
  --exclude 'qa/node_modules/' \
  --exclude 'qa/env/boxes/' \
  --exclude 'qa/env/amis/' \
  --exclude 'qa/env/working/' \
  --exclude 'vendor/' \
  ../../ working/w3tc/
```

Omit `vendor/` if you need Composer libraries inside the guest for something other than Puppeteer. Puppeteer specs do not need host `vendor/`.

**Option B — clone via `w3tc-clone`** (matches AWS): set `W3TCQA_GIT_URL` and `W3TCQA_GIT_BRANCH` (see `000-environment-example`), then:

```bash
cd /path/to/w3-total-cache/qa/env
# . ./000-environment   # if you keep those exports there
./w3tc-clone
```

After you change plugin PHP or `qa/tests` on the host, re-rsync into `working/w3tc`. Each `w3test` run remounts `/share/w3tc` into WordPress via `restore-final` → `w3tc-mount.sh`.

## 3. First boot

```bash
cd /path/to/w3-total-cache/qa/env/boxes/apache-php74-wp69-single
# apply nested-hw-virt + RAM edits if needed, then:
vagrant up --provider=virtualbox
```

Provisioning runs `vagrant-init.sh`: apt, PHP, HTTP server, WordPress, then `800-w3tc.sh` (copy plugin, `npm link` mocha/puppeteer/chai, activate W3TC, snapshot `backup-final`). First boot can take 15–30+ minutes and will download the Ubuntu box if it is not cached.

Guest Additions version warnings (box vs host VirtualBox) are usually safe if shared folders still mount.

If `800-w3tc.sh` fails:

1. Confirm `/share/w3tc` is a real directory of tens of MB, not gigabytes of `.cursor` or `node_modules`.
2. If `ln -s ... /root/w3tcqa` fails because `/root/w3tcqa` already exists as a directory:

   ```bash
   vagrant ssh -c 'sudo rm -rf /root/w3tcqa /var/www/wp-sandbox/wp-content/plugins/w3-total-cache'
   vagrant ssh -c 'sudo bash -lc "set -e; set -a; . /etc/environment; set +a; /share/scripts/init-box/800-w3tc.sh"'
   ```

3. Scripts under `/share/scripts` must be executable. Current `vagrant-init.sh` runs `chmod a+x` on them because vboxsf preserves git `0644`. AWS chmod’s after scp instead.

Success looks like plugin files under `/var/www/wp-sandbox/wp-content/plugins/w3-total-cache` and `/root/w3tcqa` pointing at the tests.

## 4. Run tests

```bash
cd /path/to/w3-total-cache/qa/env/boxes/apache-php74-wp69-single
vagrant ssh
```

In the guest:

```bash
sudo -i
set -a; . /etc/environment; set +a
cd /root/w3tcqa
```

One spec (`w3test` paths are relative to `qa/tests`):

```bash
/share/scripts/w3test pagecache/basic.js
```

A directory name runs every spec in that folder until the first failure.

`w3test` is a Ruby wrapper: it picks environments from the `/**environments: ... */` comment in the spec, restores WordPress from `backup-final`, runs Mocha/Puppeteer, then fails if the HTTP error log (plus `wp-content/debug.log`) is non-empty after filters.

### Stale `debug.log`

If Mocha passes but `w3test` prints `not empty` and an old PHP fatal, truncate live and snapshot logs, then re-run:

```bash
: > "$W3D_HTTP_SERVER_ERROR_LOG_FILENAME"
: > "${W3D_WP_CONTENT_PATH}debug.log"
: > /var/www/backup-final-wp-sandbox/wp-content/debug.log
```

`restore-final` copies `backup-final` over the site, so a fatal captured during a partial first mount will come back on every test until the snapshot log is cleared.

### Example family (CDN / Support / import)

```bash
for t in \
  support/page-render.js \
  cdn/engine-bunnycdn.js \
  cdnfsd/engine-bunnycdn.js \
  generic/import-export.js \
  cdn/headers-without-bc.js \
  cdn/headers-with-bc.js \
  cdn/ftp-test-button.js
do
  echo "===== $t ====="
  /share/scripts/w3test "$t" || echo "FAIL:$t"
done
```

FTP CDN specs need vsftpd and `for-tests.sandbox` (provisioned with the HTTP server). FSD BunnyCDN form fields live on `?page=w3tc_cdn`, not a `w3tc_cdnfsd` admin page.

## 5. Daily loop after the box exists

```bash
# host: refresh plugin + tests into the share
cd /path/to/w3-total-cache/qa/env
rsync -a --delete \
  --exclude '.git/' --exclude '.cursor/' --exclude 'node_modules/' \
  --exclude 'qa/node_modules/' --exclude 'qa/env/boxes/' \
  --exclude 'qa/env/amis/' --exclude 'qa/env/working/' \
  ../../ working/w3tc/

cd boxes/apache-php74-wp69-single
vagrant up          # no-op if already running
vagrant ssh -c 'sudo bash -lc "set -a; . /etc/environment; set +a; cd /root/w3tcqa; /share/scripts/w3test cdn/ftp-test-button.js"'
```

```bash
vagrant halt        # stop, keep the disk
vagrant destroy -f  # wipe the VM; next `vagrant up` reprovisions from scratch
```

Regenerating descriptors (`ruby ./100-generate-envs`) deletes `boxes/` and `amis/`. Halt or destroy VMs first, or you will orphan VirtualBox instances.

## Layout reminder

```text
qa/env/
  100-generate-envs     # Ruby: write amis/ + boxes/
  w3tc-clone            # optional git clone into working/w3tc
  scripts/              # mounted at /share/scripts (w3test, init-box, …)
  working/w3tc/         # mounted at /share/w3tc  (gitignored)
  boxes/<cell>/         # one Vagrantfile per cell (gitignored)
```

Related: AWS cluster notes in `qa/env/README.md`; Puppeteer inventory in `qa/COVERAGE.md`.
