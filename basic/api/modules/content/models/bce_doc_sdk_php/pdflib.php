<?php
/**
 * Created by PhpStorm.
 * User: chindor
 * Date: 2017/6/22
 * Time: 14:10
 */


/* 全局参数表*/
$globaloptlist = "searchpath=../../../resource/cmap";

/* 文档参数表 */
$docoptlist = "";

/* 页面参数表 */
$pageoptlist = "granularity=page";

$infile = $_GET['infile'];


/* 将提取出的文本以UTF-8 编码传送的浏览器 */
header("Content-type: text/html; charset=UTF-8");
print("<pre>");

$tet = TET_new();

if ($infile == "")
{
    die("<i>usage:</i> add ?infile=filename.pdf to the URL/n");
}

TET_set_option($tet, $globaloptlist);

$doc = TET_open_document($tet, $infile, $docoptlist);

if ($doc == -1)
{
    die("Error ". TET_get_errnum($tet) . " in " . TET_get_apiname($tet)
        . "(): " . TET_get_errmsg($tet) . "/n");
}

/* 获取文档的页数*/
$n_pages = TET_pcos_get_number($tet, $doc, "length:pages");

for ($pageno = 1; $pageno <= $n_pages; ++$pageno)    /* 逐页循环*/
{
    $page = TET_open_page($tet, $doc, $pageno, $pageoptlist);

    if ($page == -1)
    {
        print("Error " .  TET_get_errnum($tet) . "in " .
            TET_get_apiname($tet) . "() on page " .  $pageno . ": " .  TET_get_errmsg($tet) . "/n");
        continue;
    }

    /* 提取所有的文本段 */
    while (($text = TET_get_text($tet, $page)) != "")
    {
        /* 遍历所有字符*/
        while (($ci = TET_get_char_info($tet, $page)))
        {

            /* 提取字体名称;字符位置可以通过ci->x和ci->y取得*/
            $fontname = TET_pcos_get_string($tet, $doc,"fonts[" .  $ci->fontid .  "]/name");
        }

        print($text);
    }

    if (TET_get_errnum($tet) != 0)
    {
        print("Error " .  TET_get_errnum($tet) . " in " .  TET_get_apiname($tet) . " on page " .  $pageno  . ": " .  TET_get_errmsg($tet) . "/n");
    }

    TET_close_page($tet, $page);

    print("/n<p>");  /* add a delimiter between each zone */
}

print("</pre>");

TET_close_document($tet, $doc);

TET_delete($tet);