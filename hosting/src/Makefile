DEST="../index.php"

all : fotoo

fotoo :
	@echo -n > ${DEST}
	@echo '<?php if (isset($$_GET["js"])): header("Content-Type: text/javascript"); ?>' >> ${DEST}
	@cat upload.js >> ${DEST}
	@echo '<?php exit; endif; ?>' >> ${DEST}
	@echo '<?php if (isset($$_GET["css"])): header("Content-Type: text/css"); ?>' >> ${DEST}
	@cat style.css >> ${DEST}
	@echo '<?php exit; endif; ?>' >> ${DEST}
	@cat class.fotoo_hosting.php >> ${DEST}
	@cat lib-image/lib.image.php >> ${DEST}
	@cat index.php | sed 's/^require/\/\/require/' >> ${DEST}
