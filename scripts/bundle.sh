rm -rf .tmp
mkdir .tmp
cp -R includes .tmp
cp -R language .tmp
cp -R satispay.php .tmp
cp -R satispay.xml .tmp
(cd .tmp && zip -r archive.zip .)
