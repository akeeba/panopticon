<?php
#parse("PHP File Header.php")

#if (${NAMESPACE})
namespace ${NAMESPACE};

#end

defined('AKEEBA') || die;

enum ${NAME}#if (${BACKED_TYPE}) : ${BACKED_TYPE} #end{

}
