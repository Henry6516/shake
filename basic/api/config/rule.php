<?php
return [
    //标准的控制器/方法显示
    'api/<module:\w+>/<controller:\w+>/<action:\w+>'=>'<module>/<controller>/<action>',
    //加id参数
    'api/<controller:\w+>/<action:\w+>/<id:\d+>'=>'<module>/<controller>/<action>',
];