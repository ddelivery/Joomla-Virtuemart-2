<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="charset=UTF-8" />
<meta http-equiv="content-language" content="ru" />
<meta name="keywords" content="" />
<meta name="description" content="" />
<meta name="robots" content="all" />
<meta name="author" content="" />
<meta name="copyright" content="" />
<meta name="resourceurl" content="" />
<meta name="publisher-url" content="" />
<title>{title}</title>

<link href="style.css" rel="stylesheet" type="text/css" />

<script type="text/javascript" src="js/jquery.min.js"></script>
<script type="text/javascript" src="js/effects.js"></script>

<!--[if lte IE 6]><script src="js/DD_belatedPNG.js"></script>
<script type="text/javascript">
  DD_belatedPNG.fix('div, span, img, a, ul li, input');
</script>
<![endif]-->
{script}
{xajaxscript}
</head>
<!--[if lt IE 7 ]> <body class="no-js ie6"> <![endif]-->
<!--[if IE 7 ]>    <body class="no-js ie7"> <![endif]-->
<!--[if IE 8 ]>    <body class="no-js ie8"> <![endif]-->
<!--[if (gte IE 9)|!(IE)]><!--> <body class="no-js"> <!--<![endif]-->

<div id="container">
<div class="yzor"></div>
<div id="header">
    <div class="lang">
        <form action="" method="get" name="LangForm" id="LangForm">
            <div>
                <select id="lang" name="lang" onchange='LangForm.submit();'>
                    <option value="ru">Русский</option>
                    <option value="uk">Украинский</option>
                    <option value="en">Английский</option>
                </select>
                <label for="lang">Выберите язык:</label>
            </div>
        </form>
    </div>
    <div class="logo"><a href="/"><img src="images/logo.png" alt="" /></a></div>
    <div class="top_contacts">
        tel.: <span>+38044</span>3833683<br />
        e-mail: <a href="mailto:vip@viphalls.com.ua">vip<span>@</span>viphalls.com.ua</a>
    </div>
    <div class="clear"></div>
    <div class="top_menu">
        <div class="corner"></div>
        <ul>
            <li class="first"><a href="/">Заказать ВИП-зал</a></li>
            <li><a href="/transfers/">Трансферы в городах Украины</a></li>
            <li><a href="/scoreboard/">Табло прилетов и вылетов в а/п Украины</a></li>
            <li><a href="/contacts/">Контакты</a></li>
        </ul>
    </div>
</div>

<div id="content">
    <div class="left_column">
        
        <div class="left_menu">
            <ul>
                <li><a  href="/booking/kiev-borispol/">Киев-Борисполь Терминал С</a></li>
                <li><a href="/booking/kiev-zhulyany/">Киев-Жуляны</a></li>
                <li><a href="/booking/kiev-borispol-zod/">Киев-Борисполь ЗОД</a></li>
                <li><a href="/booking/doneck/">Донецк</a></li>
                <li><a href="/booking/dnepropetrovsk/">Днепропетровск</a></li>
                <li><a href="/booking/odessa/">Одесса</a></li>
                <li><a href="/booking/xarkov/">Харьков</a></li>
                <li><a href="/booking/simferopol/">Симферополь</a></li>
                <li><a href="/booking/lvov/">Львов</a></li>
            </ul>
        </div>
        
        <div class="links_for_left">
            <ul>
                <li>
                    <div class="ukr"><a href="/organizaciyam-ukrainy/" class="active">Организациям Украины</a></div>
                </li>
                <li>
                    <div class="rus"><a href="/specproposition/">Спецпредложение для юр. лиц РФ</a></div>
                </li>
                <li>
                    <div class="tur"><a href="/turisticheskim-firmam-ukrainy/">Туристическим фирмам Украины</a></div>
                </li>
            </ul>
        </div>
    </div>    
    <div class="main_column">

        <h1>{header}</h1>
        
        <div class="breadcrumbs"><a href="/">Главная</a> &nbsp;&nbsp;&rsaquo;&nbsp;&nbsp; <span>ВИП-сервис</span></div>
        {content}
    </div>
    
</div>    
       
<div class="clear"></div>


<div id="footer">
    <div class="copyright">All rights reserved.  2012 VIPhalls<br />Разработка сайта - <a href="#">White web</a></div>
</div>

</div>
</body>
</html>