curl -sL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs

case "${W3D_OS}" in
	"jammy") echo "Installing for jammy"
		apt install -y libnss3-dev libnss3 libxss-dev
		apt install -y cups libasound-dev libgbm1 libpangocairo-1.0-0 libx11-xcb-dev libxcb-dri3-0 libxcomposite-dev libxcursor-dev libxdamage-dev libxi-dev libxshmfence1 libxtst-dev libxrandr-dev libgtk-3-0
		# For Puppeteer 3.0.0 and up.
		apt install -y libxcb-dri3-0 libgbm1
		# For Puppeteer 6.0.0 and up.
		apt install -y libxshmfence1
		;;
	"focal") echo "Installing for focal"
		apt install -y libnss3-dev libnss3 libxss-dev
		apt install -y cups libasound-dev libgbm1 libpangocairo-1.0-0 libx11-xcb-dev libxcb-dri3-0 libxcomposite-dev libxcursor-dev libxdamage-dev libxi-dev libxshmfence1 libxtst-dev libxrandr-dev libgtk-3-0
		# For Puppeteer 3.0.0 and up.
		apt install -y libxcb-dri3-0 libgbm1
		# For Puppeteer 6.0.0 and up.
		apt install -y libxshmfence1
		;;
	*)
		apt install -y libnss3-dev libXss-dev
		apt install -y libX11-xcb-dev cups libXcomposite-dev libXcursor-dev libXdamage-dev libXi-dev libXtst-dev libXrandr-dev libasound-dev libpangocairo-1.0-0 libgdk3.0-cil
        ;;
esac


npm i puppeteer@22.6.1 -g --unsafe-perm
npm i -g mocha@5.2.0 chai@4.4.1 mocha-logger@1.0.8
