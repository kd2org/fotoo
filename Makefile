DEST="fotoo.php"

all : fotoo

fotoo :
	@echo "<?php" > ${DEST}
	@echo -n "// Fotoo Hosting single-file release version " >> ${DEST}
	@cat VERSION >> ${DEST}
	@echo -n "?>" >> ${DEST}
	@echo '<?php if (isset($$_GET["js"])): header("Content-Type: text/javascript"); ?>' >> ${DEST}
	@cat upload.js >> ${DEST}
	@echo -n '<?php exit; endif; ?>' >> ${DEST}
	@echo '<?php if (isset($$_GET["css"])): header("Content-Type: text/css"); ?>' >> ${DEST}
	@cat style.css >> ${DEST}
	@echo -n '<?php exit; endif; ?>' >> ${DEST}
	@cat class.fotoo_hosting.php >> ${DEST}
	@cat class.image.php >> ${DEST}
	@cat ZipWriter.php >> ${DEST}
	@cat index.php | sed 's/^require_once/\/\/require_once/' >> ${DEST}
