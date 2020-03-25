<?php
$content = gzencode(" n//此页已压缩", 9); //为准备压缩的内容贴上"//此页已压缩"的注释标签，然后用zlib提供的gzencode()函数执行级别为9的压缩，这个参数值范围是0-9，0 表示无压缩，9表示最大压缩，当然压缩程度越高越费CPU。
//用header()函数给浏览器发送一些头部信息，告诉浏览器这个页面已经用GZIP压缩过了！
header("Content-Encoding: gzip");
header("Vary: Accept-Encoding");
header("Content-Length: ".strlen($content));
echo $content;