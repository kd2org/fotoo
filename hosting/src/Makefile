DEST="../index.php"

all : fotoo

fotoo :
	@echo -n > ${DEST}
	@echo '<?php if (isset($_GET['js'])): ?>' >> ${DEST}
	@cat upload.js >> ${DEST}
	@echo '<?php exit; endif; ?>' >> ${DEST}
	@echo '<?php if (isset($_GET['css'])): ?>' >> ${DEST}
	@cat style.css >> ${DEST}
	@echo '<?php exit; endif; ?>' >> ${DEST}
	@cat index.php | sed 's/^require/\/\/require/' >> ${DEST}
	@cat lib-image/lib.image.php >> ${DEST}
