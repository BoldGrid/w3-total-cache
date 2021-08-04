curl -sL https://deb.nodesource.com/setup_14.x | sudo -E bash -
sudo apt-get install -y nodejs

case "${W3D_OS}" in
	"focal") echo "Installing for focal"
		apt install -y libnss3-dev libnss3 libxss-dev
		apt install -y cups libasound-dev libpangocairo-1.0-0 libx11-xcb-dev libxcomposite-dev libxcursor-dev libxdamage-dev libxi-dev libxtst-dev libxrandr-dev libgtk-3-0
		;;
	#"xenial") echo "Installing for xenial"
	#	apt install -y libnss3-dev libnss3 libxss-dev
	#	apt install -y cups libasound-dev libpangocairo-1.0-0 libx11-xcb-dev libxcomposite-dev libxcursor-dev libxdamage-dev libxi-dev libxtst-dev libxrandr-dev libgtk-3-0
	#	;;
	*)
		apt install -y libnss3-dev libXss-dev
		apt install -y libX11-xcb-dev cups libXcomposite-dev libXcursor-dev libXdamage-dev libXi-dev libXtst-dev libXrandr-dev libasound-dev libpangocairo-1.0-0 libgdk3.0-cil
        ;;
esac


npm i puppeteer@1.11.0 -g --unsafe-perm
npm i -g mocha@5.2.0 chai@4.2.0 mocha-logger@1.0.6
