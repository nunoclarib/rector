<?php

namespace RectorPrefix20210803;

if (\class_exists('Tx_Extbase_MVC_Exception_NoSuchAction')) {
    return;
}
class Tx_Extbase_MVC_Exception_NoSuchAction
{
}
\class_alias('Tx_Extbase_MVC_Exception_NoSuchAction', 'Tx_Extbase_MVC_Exception_NoSuchAction', \false);
