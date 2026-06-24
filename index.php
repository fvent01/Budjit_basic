<?php
// Root redirect — sends http://localhost/budjit/ to /budjit/public
header('Location: public/', true, 301);
exit;
