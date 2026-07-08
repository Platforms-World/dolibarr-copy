<?php
/* Copyright (C) 2004-2017	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2006		Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2007-2017	Regis Houssin			<regis.houssin@inodbox.com>
 * Copyright (C) 2011		Philippe Grand			<philippe.grand@atoo-net.com>
 * Copyright (C) 2012		Juanjo Menent			<jmenent@2byte.es>
 * Copyright (C) 2018       Ferran Marcet           <fmarcet@2byte.es>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *		\file       htdocs/takepos/css/pos.css.php
 *		\brief      File for CSS style for TakePOS
 */

//if (! defined('NOREQUIREUSER')) define('NOREQUIREUSER','1');	// Not disabled because need to load personalized language
//if (! defined('NOREQUIREDB'))   define('NOREQUIREDB','1');	// Not disabled to increase speed. Language code is found on url.
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}
//if (! defined('NOREQUIRETRAN')) define('NOREQUIRETRAN','1');	// Not disabled because need to do translations
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}
if (!defined('NOLOGIN')) {
	define('NOLOGIN', 1); // File must be accessed by logon page so without login
}
//if (! defined('NOREQUIREMENU'))   define('NOREQUIREMENU',1);  // We need top menu content
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', 1);
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}


define('ISLOADEDBYSTEELSHEET', '1');


session_cache_limiter('public');

require_once __DIR__.'/../../main.inc.php'; 
// __DIR__ allow this script to be included in custom themes
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

// Define css type
top_httphead('text/css');
// Important: Following code is to avoid page request by browser and PHP CPU at each Dolibarr page access.
if (empty($dolibarr_nocache)) {
	header('Cache-Control: max-age=10800, public, must-revalidate');
} else {
	header('Cache-Control: no-cache');
}


include DOL_DOCUMENT_ROOT.'/theme/'.$conf->theme.'/theme_vars.inc.php';
if (defined('THEME_ONLY_CONSTANT')) {
	return;
}

?>

html,body {
	box-sizing: border-box;
	padding:0px;
	margin:0;
	height:100%;
	width:100%;
}

.bodytakepos {
	background-color: var(--colorbackgrey);
}

.center {
	text-align: center;
}

button.calcbutton.poscolorblue {
	background-color: #0066AA;
}

button.calcbutton2.poscolordelete {
	background: rgb(255, 188, 185);
	color: #633;
	/*background-color: #884444;
	color: #fff;*/
}

button.calcbutton {
	display: inline-block;
	position: relative;
	padding: 0;
	line-height: normal;
	cursor: pointer;
	vertical-align: middle;
	text-align: center;
	overflow: visible; /* removes extra width in IE */
	width: calc(25% - 2px);
	height: calc(25% - 2px);
	font-weight: bold;
	background-color: #8c907e;
	color: #fff;
	/* border-color: unset; */
	border-width: 0;
	margin: 1px;
	font-size: 14pt;
	border-radius: 3px;
}

div.wrapper, div.wrapper2 {
	border-radius: 5px;
}

button.calcbutton2 {
	color: #fff;
	background-color: #5555AA;
	border-width: 0px;
	display: inline-block;
	position: relative;
	padding: 0;
	line-height: normal;
	cursor: pointer;
	vertical-align: middle;
	text-align: center;
	overflow: visible; /* removes extra width in IE */
	width: calc(25% - 2px);
	height: calc(25% - 2px);
	font-weight: bold;
	font-size: 10pt;
	margin: 1px;
	border-radius: 3px;
}
button.calcbutton2.clicked {
	background-color: #8855AA;
}

button.calcbutton2.takepos-pad-qty {
	background-color: #2563eb;
}
button.calcbutton2.takepos-pad-price {
	background-color: #7c3aed;
}
button.calcbutton2.takepos-pad-discount {
	background-color: #ea580c;
}
div.wrapper2 {
	position: relative;
}
.takepos-missing-product-image-badge {
	display: none;
	position: absolute;
	top: 6px;
	right: 6px;
	min-width: 24px;
	height: 24px;
	align-items: center;
	justify-content: center;
	border-radius: 999px;
	background: #f59e0b;
	color: #fff;
	font-size: 13px;
	box-shadow: 0 2px 8px rgba(0,0,0,.25);
	z-index: 4;
}
div.wrapper2.takepos-missing-product-image .takepos-missing-product-image-badge {
	display: flex;
}
button.calcbutton2 .iconwithlabel {
	padding-bottom: 10px;
}

button.calcbutton3 {
	display: inline-block;
	position: relative;
	padding: 0;
	line-height: normal;
	cursor: pointer;
	vertical-align: middle;
	text-align: center;
	overflow: visible; /* removes extra width in IE */
	width: calc(25% - 2px);
	height: calc(25% - 2px);
	font-size: 14pt;
	margin: 1px;
	border-radius: 3px;
}

button.productbutton {
	display: inline-block;
	position: relative;
	padding: 0;
	line-height: normal;
	cursor: pointer;
	vertical-align: middle;
	text-align: center;
	overflow: visible; /* removes extra width in IE */
	width: calc(100% - 2px);
	height: calc(100% - 2px);
	font-weight: bold;
	background-color: #a3a6a3;
	color: #fff;
	/* border-color: unset; */
	border-width: 0;
	margin: 1px;
	font-size: 14pt;
	border-radius: 3px;
}

button.actionbutton {
	background: #EABCA6;
	color: #222;
	border: 2px solid #EEE;
	min-height: 40px;
	border-radius: 3px;
}

button.actionbutton {
	display: inline-block;
	position: relative;
	padding: 0;
	line-height: normal;
	cursor: pointer;
	vertical-align: middle;
	text-align: center;
	overflow: visible; /* removes extra width in IE */
	width: calc(33.33% - 2px);
	height: calc(25% - 2px);
	margin: 1px;
	   border-width: 0;
}

button.item_value {
	background: #bbbbbb;
	border: #000000 1px solid;
	border-radius: 4px;
	padding: 8px;
}

button.item_value.selected {
	background: #ffffff;
	color: #000000;
	font-weight: bold;
}

div[aria-describedby="dialog-info"] button:before {
	content: "\f788";
	font-family: "<?php echo getDolGlobalString('MAIN_FONTAWESOME_FAMILY', 'Font Awesome 5 Free'); ?>";
	font-weight: 900;
	padding-right: 5px;
}
div[aria-describedby="dialog-info"].ui-dialog .ui-dialog-buttonpane {
	border-width: 0;
}

.takepospay {
	font-size: 1.5em;
}

.fa.fa-trash:before {
	font-size: 1.5em;
}


div.wrapper{
	float:left; /* important */
	position:relative; /* important(so we can absolutely position the description div */
	width:25%;
	height:33%;
	margin:0;
	padding:1px;
	border: 2px solid #EEE;
	/*box-shadow: 3px 3px 3px #bbb; */
	text-align: center;
	box-sizing: border-box;
	background-color:#fff;
	display: flex;
	align-items: center;
	justify-content: center;
}

div.wrapper2{
	float:left; /* important */
	position:relative; /* important(so we can absolutely position the description div */
	width:12.5%;
	height:33%;
	margin:0;
	/* padding:1px; */
	border: 2px solid #EEE;
	/*box-shadow: 3px 3px 3px #bbb;*/
	text-align: center;
	box-sizing: border-box;
	background-color:#fff;
	display: flex;
	align-items: center;
	justify-content: center;
}

img.imgwrapper {
	max-width: 100%;
	max-height: 100%;
}

button:active{
	background:black;
	color: white;
}

div.description{
	position:absolute; /* absolute position (so we can position it where we want)*/
	bottom:0px; /* position will be on bottom */
	left:0px;
	width:100%;
	/* styling below */
	background-color:black;
	/*color:white;*/
	opacity:1; /* transparency */
	/*filter:alpha(opacity=80); IE transparency */
	text-align:center;

	padding-top: 30px;
	background: -webkit-linear-gradient(top, rgba(250,250,250,0), rgba(250,250,250,0.5), rgba(250,250,250,0.95), rgba(250,250,250,1));
}

div.catwatermark{
	position:absolute;
	top:3%;
	left:3%;
	width:20%;
	background-color:black;
	color:white;
	text-align:center;
	font-size: 20px;
	display: none;
	opacity: 0.25;
}

table.postablelines tr td {
	line-height: unset;
	padding-top: 3px;
	padding-bottom: 3px;
}

.posinvoiceline td {
	height: 40px !important;
	background-color: var(--colorbacklineimpair2);
}

.postablelines td.linecolht {
	line-height: 1.3em !important;
}

div.paymentbordline
{
	width:calc(50% - 16px);
	background-color:#aaa;
	border-radius: 8px;
	margin-bottom: 4px;
	display: inline-block;
	padding: 5px;
}

@media only screen and (max-aspect-ratio: 6/4) {
	div.description{
	min-height:20%;
	}
}

.container{
	width: 100%;
	height: 100%;
	margin: 0 auto;
	<?php
	if (getDolGlobalString('TAKEPOS_USE_ARROW_ON_NAVBAR')) {
		?>
		overflow-x: hidden;
		overfloy-y: scroll;
		<?php
	} else {
		?>
		overflow: visible;
		<?php
	}
	?>
	box-sizing: border-box;
}

.row1{
	margin: 0 auto;
	width: 100%;
	height: 34%;
}

.row1withhead{
	margin: 0 auto;
	width: 100%;
	height: calc(45% - 50px);
	padding-top: 9px;
}

.row2{
	margin: 0 auto;
	width: 100%;
	height: 66%;
}

.row2withhead{
	margin: 0 auto;
	width: 100%;
	height: 55%;
	overflow-x: hidden;
}

.div1{
	height:100%;
	width: 34%;
	float: left;
	text-align: center;
	box-sizing: border-box;
	overflow: auto;
	/* background-color:white; */
	padding-top: 1px;
	padding-bottom: 0;
	min-height: 180px;
}

.div2{
	height: 100%;
	width: 33%;
	font-size: 0;
	float: left;
	box-sizing: border-box;
	padding-top: 0;
	padding-bottom: 0;
	min-height: 180px;
}

.div3{
	height: 100%;
	width: 33%;
	float: left;
	box-sizing: border-box;
	padding-top: 0;
	padding-bottom: 0;
}

.div4{
	height: 100%;
	width: 34%;
	float: left;
	box-sizing: border-box;
	font-size: 6px;
	padding-top: 10px;
	padding-bottom: 10px;
}

.div5{
	height: 100%;
	width: 66%;
	float: left;
	box-sizing: border-box;
	font-size: 6px;
	padding-top:10px;
	padding-bottom:10px;
}

.div1, .div2, .div3, .div4, .div5 {
	padding-right: 5px;
	padding-left: 5px;
}
.div1, .div4 {
	padding-left: 8px;
}
.div3, .div5 {
	padding-right: 8px;
}

tr.selected, tr.selected td {
	background-color: var(--colorbacklinepairchecked) !important;
}
.order td {
	color: green;
	/* background-color: #f5f5f5; */
}

.colorwhite {
	color: white;
}
.colorred {
	color: red;
}
.colorgreen {
	color: green;
}
.poscolordelete {
	color: #844;
}
.poscolorgreen {
	color: #060;
}
.poscolorblue {
	color: #006;
}

.centerinmiddle {
	position: relative;
	/* transform: translate(0,-50%);
	top: 50%; */
}
.trunc {
	white-space: nowrap;
	text-overflow: ellipsis;
	overflow: hidden;
}

p.description_content{
	padding:10px;
	margin:0px;
}
div.description_content {
	display: -webkit-box;
	-webkit-box-orient: vertical;
	-webkit-line-clamp: <?php echo getDolGlobalInt('TAKEPOS_LINES_TO_SHOW', 2); ?>;
	overflow: hidden;
	padding-left: 2px;
	padding-right: 2px;
}

.header{
	margin: 0 auto;
	width: 100%;
	height: 52px;
	background: rgb(60,70,100);
}

.topnav-left {
	float: left;
}
.topnav-right {

}

.topnav div.login_block_other, .topnav div.login_block_user {
	max-width: unset;
	width: unset;
}
.topnav{
	background: var(--colorbackhmenu1);
	overflow: hidden;
	height: 100%;
}
.topnav .tmenu {
	display: block;
}

.topnav a{
	float: left;
	color: #f2f2f2;
	text-decoration: none;
}
.topnav .login_block_other a {
	padding: 5px 10px;
	margin-left: 4px;
	font-size: 1.3em;
}
.topnav div.login_block_user {
	display: inline-block;
	vertical-align: middle;
	line-height: 50px;
	height: 50px;
}
.userimg.atoplogin img.userphoto, .userimgatoplogin img.userphoto {
	width: 30px;
	height: 30px;
	vertical-align: middle;
}

@media screen and (max-width: 767px) {
	.topnav .login_block_other a {
		padding: 5px 5px;
		font-size: 1.2em;
	}

	.div1, .div4 {
		padding-left: 5px;
	}
	.div3, .div5 {
		padding-right: 5px;
	}
}

.topnav-right > a {
	font-size: 17px;
}

.topnav-left a {
	padding: 7px 4px 7px 4px;
	margin: 8px;
	margin-left: 5px;
	margin-right: 5px;
	border-radius: 3px;
}
.topnav-left a:hover:not(.nohover), .topnav .login_block_other a:hover:not(.nohover) {
	background-color: #ddd;
	color: black;
}

.topnav-right{
	float: right;
}

.topnav input[type="text"] {
	background-color: #fff;
	color: #000;
	float: left;
	border-bottom: none !important;
	margin-left: 6px;
	font-size: 1.3em;
	max-width: 250px;
	border-radius: 5px;
}


.login_block_other.takepos {
	margin-top: 5px;
}


div#moreinfo, div#infowarehouse {
	color: #aaa;
	padding: 0 8px 0 8px;
}

.basketselected {
	font-weight: bold;
	/* text-decoration: underline; */
}
.basketnotselected {
	opacity: 0.8;
}

.productprice {
	position: absolute;
	top: 5px;
	right: 5px;
	background: var(--colorbackhmenu1);
	color: var(--colortextbackhmenu);
	font-size: 2em;
	padding: 4px;
	border-radius: 2px;
	opacity: 0.9;
	padding-left: 6px;
	padding-right: 6px;
}


@media screen and (min-width: 892px) {
	.actionbutton{
		font-size: 13px;
	}
	div.description{
		font-size: 15px;
	}
	.invoice{
		font-size: 14px;
	}
}

@media (max-width: 891px) and (min-width: 386px) {
	.actionbutton{
		font-size: 12px;
	}
	div.description{
		font-size: 13px;
	}
	.invoice{
		font-size: 12px;
	}
}

@media screen and (max-width: 385px){
	.actionbutton{
		font-size: 10px;
	}
	div.description{
		font-size: 11px;
	}
	.invoice{
		font-size: 10px;
	}
}

/* For small screens */

@media screen and (max-width: 1024px) {
	.topnav input[type="text"] {
		max-width: 150px;
	}
}

@media screen and (max-width: 767px) {
	.header {
		position: sticky;
		top: 0;
		z-index: 10;
	}

	.topnav input[type="text"] {
		max-width: 90px;
		font-size: 1.15em;
	}

	.topnav-right {
		float: unset;
	}
	.header {
		height: unset;
	}
	div.container {
		overflow-x: scroll;
	}
	div.wrapper {
		width: 50%;
	}
	div.wrapper2 {
		width: 25%;
	}

	.row1withhead{
		height: unset;
	}

	div#moreinfo, div#infowarehouse {
		padding: 0 5px 0 5px;
	}

	div.div1 {
		padding-bottom: 0;
		margin-bottom: 10px;
	}
	div.div1, div.div2, div.div3 {
		width: 100%;
	}

	div.row1 {
		height: unset;
	}

	div.div2 {
		min-height: unset;
	}

	div.div3 {
		margin-top: 8px;
		height: unset;
	}

	button.calcbutton, button.calcbutton2 {
		min-height: 30px;
	}

	.takepospay {
		font-size: 1.2em;
	}

	button.actionbutton {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		padding-left: 4px;
		padding-right: 4px;
		min-height: 30px;
	}
}


body.takepos-product-studio-active {
	overflow: hidden;
}

.takepos-product-studio-backdrop {
	position: fixed;
	inset: 0;
	background: rgba(8, 14, 26, 0.48);
	opacity: 0;
	pointer-events: none;
	transition: opacity 180ms ease;
	z-index: 38;
}

.takepos-product-studio-backdrop.open {
	opacity: 1;
	pointer-events: auto;
}

.takepos-product-studio-panel {
	position: fixed;
	top: 0;
	right: 0;
	height: 100vh;
	width: min(430px, 94vw);
	background: linear-gradient(160deg, #102038 0%, #1a3559 55%, #20416c 100%);
	box-shadow: -24px 0 50px rgba(0, 0, 0, 0.35);
	transform: translateX(110%);
	transition: transform 220ms ease;
	z-index: 39;
	display: flex;
	flex-direction: column;
	color: #f4f7fb;
}

.takepos-product-studio-panel.open {
	transform: translateX(0);
}

.takepos-product-studio-head {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	padding: 18px 18px 12px;
	border-bottom: 1px solid rgba(255, 255, 255, 0.15);
}

.takepos-product-studio-head h3 {
	margin: 0;
	font-size: 1.35rem;
	letter-spacing: 0.4px;
}

.takepos-product-studio-head p {
	margin: 7px 0 0;
	opacity: 0.88;
	font-size: 0.9rem;
	line-height: 1.35;
}

.takepos-product-studio-close {
	border: none;
	background: rgba(255, 255, 255, 0.12);
	color: #fff;
	width: 34px;
	height: 34px;
	border-radius: 999px;
	cursor: pointer;
}

.takepos-product-studio-close:hover {
	background: rgba(255, 255, 255, 0.2);
}

.takepos-product-studio-links {
	padding: 14px;
	overflow-y: auto;
	flex: 1;
	display: flex;
	flex-direction: column;
	gap: 9px;
}

.takepos-product-studio-link {
	border: 1px solid rgba(255, 255, 255, 0.14);
	border-radius: 12px;
	background: rgba(255, 255, 255, 0.08);
	color: #fff;
	padding: 12px;
	display: flex;
	gap: 12px;
	align-items: center;
	text-align: left;
	cursor: pointer;
	transition: transform 150ms ease, background-color 150ms ease, border-color 150ms ease;
}

.takepos-product-studio-link:hover {
	transform: translateY(-1px);
	background: rgba(255, 255, 255, 0.14);
}

.takepos-product-studio-link-create {
	border-color: rgba(69, 208, 144, 0.42);
}

.takepos-product-studio-link-manage {
	border-color: rgba(92, 178, 255, 0.42);
}

.takepos-product-studio-icon {
	width: 34px;
	min-width: 34px;
	height: 34px;
	border-radius: 9px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	background: rgba(255, 255, 255, 0.16);
}

.takepos-product-studio-copy {
	display: flex;
	flex-direction: column;
	gap: 3px;
	min-width: 0;
}

.takepos-product-studio-label {
	font-size: 0.96rem;
	font-weight: 700;
	line-height: 1.2;
}

.takepos-product-studio-desc {
	font-size: 0.78rem;
	opacity: 0.85;
	line-height: 1.25;
}

.takepos-product-studio-footer {
	padding: 14px;
	border-top: 1px solid rgba(255, 255, 255, 0.15);
}

.takepos-product-studio-refresh {
	width: 100%;
	border: none;
	border-radius: 10px;
	padding: 11px 13px;
	font-weight: 700;
	font-size: 0.92rem;
	cursor: pointer;
	color: #13263f;
	background: linear-gradient(90deg, #7ed0ff 0%, #60ffa5 100%);
}

.takepos-product-studio-refresh span {
	margin-right: 6px;
}

.takepos-product-studio-toggle {
	position: fixed;
	right: 14px;
	bottom: 16px;
	z-index: 37;
	border: none;
	border-radius: 999px;
	padding: 10px 15px;
	background: linear-gradient(135deg, #f39b46 0%, #f1643b 100%);
	color: #fff;
	font-weight: 700;
	display: inline-flex;
	align-items: center;
	gap: 8px;
	box-shadow: 0 11px 25px rgba(186, 60, 36, 0.4);
	cursor: pointer;
}

.takepos-product-studio-toggle:hover {
	filter: brightness(1.05);
}

@media screen and (max-width: 767px) {
	.takepos-product-studio-toggle {
		right: 8px;
		bottom: 10px;
		padding: 9px 12px;
	}

	.takepos-product-studio-toggle-text {
		display: none;
	}

	.takepos-product-studio-panel {
		width: min(420px, 96vw);
	}
}
/* Modal box */
.modal {
  display: none; /* Hidden by default */
  position: fixed;
  z-index: 20;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  overflow: auto;
  background-color: rgb(0,0,0);
  background-color: rgba(0,0,0,0.4);
}

/* The Close Button */
.close {
  color: #aaa;
  float: right;
  font-size: 28px;
  font-weight: bold;
}

.close:hover,
.close:focus {
  color: black;
  text-decoration: none;
  cursor: pointer;
}

.modal-header {
  padding: 2px 16px;
  background-color: #2b4161;
  color: white;
}

.modal-body {padding: 2px 16px;}

.modal-content {
  position: relative;
  background-color: #fefefe;
  margin: 15% auto; /* 15% from the top and centered */
  padding: 0;
  border: 1px solid #888;
  width: 40%;
  box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2),0 6px 20px 0 rgba(0,0,0,0.19);
  animation-name: animatetop;
  animation-duration: 0.4s;
  min-width: 200px;
}

#ModalCalculator {
	z-index: 24;
}

#ModalCalculator .takepos-calc-modal-content {
	width: min(420px, 92vw);
	max-width: 420px;
	margin: 6vh auto;
	border: 1px solid #5f82b4;
	border-radius: 16px;
	overflow: hidden;
	box-shadow: 0 22px 50px rgba(22, 39, 72, 0.28);
	background: #f7f9fd;
}

#ModalCalculator .takepos-calc-modal-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 14px 18px;
	background: linear-gradient(135deg, #5b86c5, #6f97d5);
	border-bottom: 1px solid rgba(255,255,255,0.18);
}

#ModalCalculator .takepos-calc-modal-header h3 {
	margin: 0;
	font-size: 24px;
	font-weight: 700;
	color: #fff;
}

#ModalCalculator .takepos-calc-modal-header .close {
	color: #fff;
	font-size: 34px;
	line-height: 1;
	text-shadow: none;
}

#ModalCalculator .takepos-calc-modal-body {
	padding: 18px;
	background: #f7f9fd;
}

#ModalCalculator .takepos-calc-display {
	width: 100%;
	box-sizing: border-box;
	font-size: 28px;
	font-weight: 700;
	padding: 14px 16px;
	text-align: right;
	margin-bottom: 14px;
	border: 1px solid #c8d6ea;
	border-radius: 12px;
	background: #fff;
	color: #23324c;
	box-shadow: inset 0 1px 2px rgba(35, 50, 76, 0.06);
}

#ModalCalculator .takepos-calc-grid {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 10px;
}

#ModalCalculator .takepos-calc-actions {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 10px;
	margin-top: 14px;
}

#ModalCalculator .takepos-calc-btn {
	width: 100%;
	margin: 0;
	padding: 14px 10px;
	border: 1px solid #d2dceb;
	border-radius: 12px;
	background: #ffffff;
	color: #20314d;
	font-size: 22px;
	font-weight: 700;
	line-height: 1.1;
	box-shadow: 0 2px 6px rgba(25, 42, 70, 0.08);
}

#ModalCalculator .takepos-calc-btn:hover,
#ModalCalculator .takepos-calc-btn:focus {
	filter: none;
	background: #f0f5fd;
	border-color: #9fb6db;
}

#ModalCalculator .takepos-calc-operator {
	background: #edf3fc;
	color: #2f5e9f;
}

#ModalCalculator .takepos-calc-clear {
	background: #fff1f0;
	color: #b44434;
	border-color: #e8bbb5;
}

#ModalCalculator .takepos-calc-equals {
	background: #5f82b4;
	color: #fff;
	border-color: #5f82b4;
}

#ModalCalculator .takepos-calc-close {
	background: #eef2f7;
	color: #40546f;
}

@keyframes animatetop {
  from {top: -300px; opacity: 0}
  to {top: 0; opacity: 1}
}

.block {
  display: block;
  width: 100%;
  border: none;
  color: white;
  background-color: #8c907e;
  padding: 14px 0px;
  font-size: 16px;
  cursor: pointer;
  text-align: center;
  margin: 2px;
}

.splitsale {
	float: left;
	width: 100%;
	height: 100%;
	overflow: auto;
}

.rowsplit {
	width: 100%;
	height: 40%;
}

.headersplit {
	height: 10%;
	width: 100%;
	padding-top: 20px;
	padding-bottom: 2px;
}

.headercontent {
	margin: auto;
	width: 50%;
	border: 3px solid black;
	text-align: center;
	font-size: 150%;
	background-color: rgb(233,234,237);
}


@media only screen and (max-width: 767px)
{
	.headercontent {
		width: 80%;
	}

	.headersplit .headercontent {
		font-size: 1em;
	}
}


.row:after {
  content: "";
  display: table;
  clear: both;
}

.div5 .imgadd {
	display: none;
}


@media screen and (max-width: 767px) {
	.div4 {
		height: auto;
		width: 100%;
		float: left;
		box-sizing: border-box;
		font-size: 6px;
		padding-top: 10px;
		padding-bottom: 2px;
		margin-left: 2px;
	}

	.div4 .wrapper.divempty, .div4 img, .div4 .wrapper:nth-last-child(1), .div4 .wrapper:nth-last-child(2), #prodiv22, #prodiv23, .catwatermark {
		display: none!important;
	}

	.tab-category {
		float: left;
		position: relative;
		width: 25%;
		height: 33%;
		margin: 0;
		padding: 1px;
		border: 2px solid #EEE;
		text-align: center;
		box-sizing: border-box;
		background-color: #fff;
	}

	.div4 .wrapper, .tab-category {
		width: auto;
		height: auto;
		padding: 6px;
		text-align: center;
		cursor: pointer;
		border: 1px solid #FFF!important;
		border-top: 3px solid #FFF!important;
	}

	.div4 .tab-category.active {
		border-right: 1px solid #CCC !important;
		border-left: 1px solid #CCC !important;
		border-top: 3px solid var(--colorbackhmenu1) !important;
	}

	.div5 {
		height: 100%;
		width: 100%;
		padding-top: 0px;
	}

	div.description {
		position: initial;
		width: auto;
		background-color: black;
		opacity: 1;
		text-align: center;
		padding-top: 0px;
		background: -webkit-linear-gradient(top, rgba(250,250,250,0), rgba(250,250,250,0.5), rgba(250,250,250,0.95), rgba(250,250,250,1));
	}

	.div5 .description .description_content {
		font-weight: bold;
		font-size: 14px;
		padding-left: 10px;
	}

	.div5 .wrapper2 {
		width: 100%;
		display: inline-flex;
		align-items: center;
		padding: 10px;
		justify-content: normal;
	}

	.div5 .wrapper2.divempty {
		display: none;
	}

	div.wrapper2 {
		float: none;
	}

	.div5 .arrow {
		width: auto;
		height: auto;
		display: none!important;
	}

	.div5 .arrow .centerinmiddle {
		transform: translate(0, 0);
	}

	.div5 .imgadd {
		display: flex;
	}

	div.wrapper2{
		height: 40px;
	}
}


<?php
if (!getDolGlobalString('TAKEPOS_USE_ARROW_ON_NAVBAR')) {
	?>

.arrows {
	display: none;
}

	<?php
} else { ?>
.indicator {
	background: #00000042;
	padding: 15px 5px;
	cursor: pointer;
	position:absolute;
}

.indicator.left {
	left:0;
}

.indicator.right {
	right:0;
}

.indicator:hover {
	background: #000000;
}

.indicator i {
	color: white;
}

.topnav-left {
	margin-left: 20px;
}

.topnav-right {
	margin-right: 20px;
}

/* For Header Scroll */
html {
  scroll-behavior: smooth;
}

.topnav {
  scroll-behavior: smooth;
}

.header {
	height: unset;
}

.topnav {
	width: 100%;
	display: flex;
	align-items: center;
	white-space: nowrap;
	overflow-x: hidden;
	overflow-y: hidden;
	scroll-behavior: smooth;
	gap: 10px;
}

.topnav-left {
	display: flex;
	align-items: center;
	flex: 1 1 auto;
	min-width: 0;
	overflow-x: auto;
	overflow-y: hidden;
	white-space: nowrap;
	float: none;
	margin-right: 10px;
	scroll-behavior: smooth;
	-webkit-overflow-scrolling: touch;
}

.topnav-right {
	display: flex;
	align-items: center;
	flex: 0 0 auto;
	white-space: nowrap;
	float: none;
	margin-left: auto;
	position: relative;
	z-index: 20;
	background: var(--colorbackhmenu1);
	overflow: visible;
	max-width: none;
	padding-left: 8px;
}

.topnav-left #shoppingcart {
	display: inline-flex;
}

.topnav-right .login_block_other,
.topnav-right .login_block_user {
	display: flex;
	align-items: center;
	white-space: nowrap;
	flex: 0 0 auto;
}

.topnav-right a,
.topnav-right input,
.topnav-right span {
	flex-shrink: 0;
}

::-webkit-scrollbar {
  width: 8px;
}


::-webkit-scrollbar-track {
  background: #f1f1f1;
}


::-webkit-scrollbar-thumb {
  background: #888;
}

.topnav-left::-webkit-scrollbar-track{
  background: #eeeeee;
}

.topnav-left::-webkit-scrollbar{
  width: 1px;
  height: 1px;
  background: #F5F5F5;
}

.topnav-left::-webkit-scrollbar-thumb{
	background: #f9171700;
}

.topnav.overflow .arrows {
	display: flex;
}

<?php } ?>

/* ── TakePOS Hold & Feedback additions ─────────────────────────────────── */

/* Feedback bar — appears at top of POS screen */
.takepos-feedback-bar {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	z-index: 9999;
	padding: 10px 18px;
	font-size: 1em;
	font-weight: 600;
	text-align: center;
	border-radius: 0 0 4px 4px;
	box-shadow: 0 2px 8px rgba(0,0,0,0.18);
	transition: opacity 0.3s;
}
.takepos-feedback-success { background: #2e7d32; color: #fff; }
.takepos-feedback-error   { background: #c62828; color: #fff; }
.takepos-feedback-warning { background: #e65100; color: #fff; }
.takepos-feedback-info    { background: #1565c0; color: #fff; }

/* Held sales table inside modal */
.takepos-held-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 0.93em;
}
.takepos-held-table th {
	background: #444;
	color: #fff;
	padding: 6px 10px;
	text-align: left;
}
.takepos-held-table td {
	padding: 7px 10px;
	border-bottom: 1px solid #ddd;
}
.takepos-held-table tr:hover td {
	background: #f5f5f5;
}


.takepos-shortcut-badge,
.takepos-pad-badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 22px;
	height: 18px;
	padding: 0 5px;
	margin-right: 8px;
	border-radius: 999px;
	background: rgba(17, 24, 39, 0.10);
	color: #1f2937;
	font-size: 11px;
	font-weight: 700;
	line-height: 1;
	vertical-align: middle;
}

.takepos-pad-badge {
	position: absolute;
	top: 6px;
	right: 6px;
	margin-right: 0;
	background: rgba(255, 255, 255, 0.85);
}

.calcbutton,
.calcbutton2 {
	position: relative;
}


/* 2026-03 package fixes */
.div3 {
	display: flex;
	flex-wrap: wrap;
	align-content: flex-start;
	overflow-y: auto;
}

.div3 button.actionbutton {
	width: calc(33.33% - 4px);
	min-height: 56px;
	height: auto;
	padding: 6px 4px;
}

#topnav-right .login_block_other {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	flex-wrap: nowrap;
}

#topnav-right .login_block_other input[type="text"] {
	min-width: 180px;
}

div.description_content,
button.productbutton {
	display: block !important;
	visibility: visible !important;
	opacity: 1 !important;
}

button.productbutton {
	white-space: normal;
	word-break: break-word;
}


/* 2026-03-22 reliability fixes */
#topnav-right {
	position: relative;
	right: auto;
	top: auto;
	z-index: 20;
	margin-left: auto;
	display: flex;
	align-items: center;
	gap: 6px;
	flex: 0 1 58vw;
	max-width: 58vw;
	min-width: 0;
	background: var(--colorbackhmenu1);
	overflow-x: auto;
	overflow-y: visible;
	scrollbar-width: none;
	-ms-overflow-style: none;
}
#topnav-right::-webkit-scrollbar {
	display: none;
	width: 0;
	height: 0;
}
#topnav-right .login_block_other {
	flex-wrap: nowrap;
	overflow: visible;
	min-width: 0;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	flex: 0 0 auto;
}
#topnav-right .login_block_other input[type="text"] {
	min-width: 110px;
	width: clamp(110px, 14vw, 190px);
	max-width: 190px;
}
#topnav-right .login_block_user {
	display: inline-flex;
	align-items: center;
	align-self: center;
	position: relative;
	right: auto;
	height: auto !important;
	line-height: normal !important;
	margin: 0;
	background: transparent;
	padding-left: 6px;
	flex: 0 0 auto;
	min-width: 0;
}
.topnav-right .login_block_user > div,
.topnav-right .login_block_user > a,
.topnav-right .login_block_user > span {
	display: inline-flex !important;
	align-items: center !important;
	align-self: center !important;
	vertical-align: middle !important;
	position: static !important;
	top: auto !important;
	bottom: auto !important;
	margin: 0 !important;
	padding: 0 !important;
	min-height: 34px;
	line-height: normal !important;
	background: transparent !important;
	border: 0 !important;
	box-shadow: none !important;
	min-width: 0;
}
.topnav-right .login_block_user > div > a,
.topnav-right .login_block_user > div > span,
.topnav-right .login_block_user > a > span {
	display: inline-flex;
	align-items: center;
	align-self: center;
	vertical-align: middle;
	margin: 0 !important;
}
.topnav-right .login_block_user > a,
.topnav-right .login_block_user .atoplogin,
.topnav-right .login_block_user .userimg,
.topnav-right .login_block_user .userimgatoplogin {
	min-height: 34px;
	height: auto !important;
	line-height: normal !important;
	margin: 0 !important;
	padding: 4px 8px !important;
	border-radius: 6px;
	background: transparent !important;
	color: #f2f2f2 !important;
	box-shadow: none !important;
	border: none !important;
}
.topnav-right .login_block_user .userimg.atoplogin img.userphoto,
.topnav-right .login_block_user .userimgatoplogin img.userphoto {
	margin: 0;
}
.topnav-right .login_block_user .dropdown-menu,
.topnav-right .login_block_user .menu_tdo,
.topnav-right .login_block_user .menu_tderight {
	position: absolute !important;
}

@media screen and (max-width: 1365px) {
	#topnav-right {
		flex-basis: 64vw;
		max-width: 64vw;
	}
}

@media screen and (max-width: 1100px) {
	#topnav-right {
		flex-basis: 70vw;
		max-width: 70vw;
	}
	#topnav-right .login_block_other input[type="text"] {
		width: clamp(96px, 12vw, 150px);
		max-width: 150px;
		min-width: 96px;
	}
}


/* 2026-04-03 safe user menu dropdown fix (LTR + RTL) */
.header,
.topnav,
#topnav-right,
.topnav-right,
.topnav-right .login_block_user {
	overflow: visible !important;
}

/* 2026-04-03 layered header fix: keep user dropdown above POS action tiles */
.header,
.topnav,
#topnav-right,
.topnav-right {
	position: relative;
}

.header {
	z-index: 20000;
	isolation: isolate;
}

.topnav,
#topnav-right,
.topnav-right {
	z-index: 20001;
}

.topnav-right .login_block_user {
	position: relative;
	z-index: 20002;
	isolation: isolate;
}

.topnav-right .login_block_user > a,
.topnav-right .login_block_user .atoplogin,
.topnav-right .login_block_user .userimg,
.topnav-right .login_block_user .userimgatoplogin {
	display: inline-flex;
	align-items: center;
}

.topnav-right .login_block_user a {
	float: none;
}

.topnav-right .login_block_user ul,
.topnav-right .login_block_user .dropdown-menu,
.topnav-right .login_block_user .menu_tdo,
.topnav-right .login_block_user .menu_tderight,
.topnav-right .login_block_user .takepos-user-menu-overlay-target {
	z-index: 20002;
}

.topnav-right .login_block_user ul {
	list-style: none;
	margin: 0;
	padding: 6px 0;
}

.topnav-right .login_block_user .dropdown-menu,
.topnav-right .login_block_user .menu_tdo,
.topnav-right .login_block_user .menu_tderight,
.topnav-right .login_block_user .takepos-user-menu-overlay-target {
	position: absolute !important;
	top: calc(100% + 6px) !important;
	right: 0 !important;
	left: auto !important;
	min-width: 220px;
	max-width: min(320px, 90vw);
	background: #ffffff !important;
	color: #222 !important;
	border: 1px solid rgba(0, 0, 0, 0.12);
	border-radius: 10px;
	box-shadow: 0 10px 28px rgba(0, 0, 0, 0.20);
	padding: 6px 0;
	text-align: left;
	direction: ltr;
}

.topnav-right .login_block_user .dropdown-menu a,
.topnav-right .login_block_user .menu_tdo a,
.topnav-right .login_block_user .menu_tderight a,
.topnav-right .login_block_user .takepos-user-menu-overlay-target a,
.topnav-right .login_block_user .dropdown-menu .tmenu,
.topnav-right .login_block_user .menu_tdo .tmenu,
.topnav-right .login_block_user .menu_tderight .tmenu,
.topnav-right .login_block_user .takepos-user-menu-overlay-target .tmenu {
	display: flex !important;
	align-items: center;
	gap: 8px;
	float: none !important;
	width: 100%;
	box-sizing: border-box;
	padding: 10px 14px;
	color: #222 !important;
	background: transparent;
	text-decoration: none;
	line-height: 1.35;
	white-space: normal;
}

.topnav-right .login_block_user .dropdown-menu a:hover,
.topnav-right .login_block_user .menu_tdo a:hover,
.topnav-right .login_block_user .menu_tderight a:hover,
.topnav-right .login_block_user .takepos-user-menu-overlay-target a:hover,
.topnav-right .login_block_user .dropdown-menu .tmenu:hover,
.topnav-right .login_block_user .menu_tdo .tmenu:hover,
.topnav-right .login_block_user .menu_tderight .tmenu:hover,
.topnav-right .login_block_user .takepos-user-menu-overlay-target .tmenu:hover {
	background: #f5f7fb !important;
	color: #111 !important;
}

[dir="rtl"] .topnav-right .login_block_user .dropdown-menu,
[dir="rtl"] .topnav-right .login_block_user .menu_tdo,
[dir="rtl"] .topnav-right .login_block_user .menu_tderight,
[dir="rtl"] .topnav-right .login_block_user .takepos-user-menu-overlay-target {
	right: 0 !important;
	left: auto !important;
	text-align: right;
	direction: rtl;
}

[dir="rtl"] .topnav-right .login_block_user .dropdown-menu a,
[dir="rtl"] .topnav-right .login_block_user .menu_tdo a,
[dir="rtl"] .topnav-right .login_block_user .menu_tderight a,
[dir="rtl"] .topnav-right .login_block_user .takepos-user-menu-overlay-target a,
[dir="rtl"] .topnav-right .login_block_user .dropdown-menu .tmenu,
[dir="rtl"] .topnav-right .login_block_user .menu_tdo .tmenu,
[dir="rtl"] .topnav-right .login_block_user .menu_tderight .tmenu,
[dir="rtl"] .topnav-right .login_block_user .takepos-user-menu-overlay-target .tmenu {
	text-align: right;
	justify-content: flex-start;
}

.topnav-right .login_block_user > ul {
	position: static !important;
	top: auto !important;
	right: auto !important;
	left: auto !important;
	display: inline-flex;
	align-items: center;
	gap: 0;
	padding: 0 !important;
	margin: 0 !important;
	min-width: 0;
	max-width: none;
	background: transparent !important;
	border: none !important;
	box-shadow: none !important;
	border-radius: 0 !important;
}

.topnav-right .login_block_user > ul > li,
.topnav-right .login_block_user > ul > li > a {
	display: inline-flex;
	align-items: center;
}

#topnav-right .login_block_user > #topmenu-login-dropdown {
	position: relative !important;
	top: auto !important;
	right: auto !important;
	left: auto !important;
	bottom: auto !important;
	display: inline-flex !important;
	align-items: center !important;
	align-self: center !important;
	float: none !important;
	width: auto !important;
	min-width: 0 !important;
	max-width: none !important;
	margin: 0 !important;
	padding: 0 0 0 6px !important;
	line-height: normal !important;
	height: auto !important;
	background: transparent !important;
	border: 0 !important;
	border-radius: 0 !important;
	box-shadow: none !important;
}

#topnav-right .login_block_user > #topmenu-login-dropdown > a.login-dropdown-a,
#topnav-right .login_block_user > #topmenu-login-dropdown > a.dropdown-toggle {
	display: inline-flex !important;
	align-items: center !important;
	gap: 8px;
	min-height: 34px;
	height: auto !important;
	margin: 0 !important;
	padding: 4px 8px !important;
	line-height: normal !important;
	background: transparent !important;
	border: 0 !important;
	border-radius: 6px !important;
	box-shadow: none !important;
	color: #f2f2f2 !important;
	text-decoration: none !important;
	white-space: nowrap;
}

#topnav-right .login_block_user > #topmenu-login-dropdown.open > .dropdown-menu {
	position: absolute !important;
	top: calc(100% + 6px) !important;
	right: 0 !important;
	left: auto !important;
}

#topnav-right .login_block_user > #topmenu-login-dropdown > .dropdown-menu {
	display: none;
}

#topnav-right .login_block_user > #topmenu-login-dropdown.open > .dropdown-menu {
	display: block !important;
}

.topnav-right .login_block_user script {
	display: none !important;
}


/* 2026-04-04 RTL user dropdown overlay fix v3 */
.header,
.topnav,
#topnav-right,
.topnav-right,
.topnav-right .login_block_user {
	overflow: visible !important;
}

body.tp-rtl .topnav-right .login_block_user {
	position: relative;
	z-index: 1001;
}

body.tp-rtl .takepos-user-menu-overlay-target {
	position: fixed !important;
	z-index: 2147483001 !important;
	min-width: 220px;
	max-width: min(320px, calc(100vw - 16px));
	background: #ffffff !important;
	color: #222 !important;
	border: 1px solid rgba(0, 0, 0, 0.14);
	border-radius: 10px;
	box-shadow: 0 12px 30px rgba(0, 0, 0, 0.24);
	padding: 6px 0;
	text-align: right;
	direction: rtl;
}

body.tp-rtl .takepos-user-menu-overlay-target a,
body.tp-rtl .takepos-user-menu-overlay-target .tmenu,
body.tp-rtl .takepos-user-menu-overlay-target .user-body a {
	display: flex !important;
	align-items: center;
	gap: 8px;
	float: none !important;
	width: 100%;
	box-sizing: border-box;
	padding: 10px 14px !important;
	margin: 0 !important;
	line-height: 1.35;
	text-decoration: none !important;
	color: #1f2937 !important;
	background: transparent !important;
	text-align: right;
}

body.tp-rtl .takepos-user-menu-overlay-target a:hover,
body.tp-rtl .takepos-user-menu-overlay-target .tmenu:hover,
body.tp-rtl .takepos-user-menu-overlay-target .user-body a:hover {
	background: #f3f6fb !important;
	color: #111827 !important;
}


/* 2026-04-19 POS catalog layout and visual consistency fix
 * Converts the product/category area from fragile float sizing to a stable grid on desktop/tablet.
 * Keeps existing ids/classes and JavaScript behaviour intact.
 */
@media screen and (min-width: 768px) {
	.row2,
	.row2withhead {
		display: flex;
		align-items: stretch;
		gap: 0;
		overflow: hidden;
		min-height: 300px;
	}

	.div4,
	.div5 {
		float: none !important;
		height: 100%;
		min-height: 0;
		overflow-y: auto;
		overflow-x: hidden;
		padding: 8px;
		box-sizing: border-box;
		font-size: 12px;
	}

	.div4 {
		flex: 0 0 28%;
		max-width: 28%;
		width: 28%;
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
		grid-auto-rows: minmax(94px, 1fr);
		gap: 8px;
		align-content: start;
	}

	.div5 {
		flex: 1 1 auto;
		width: auto;
		max-width: none;
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(145px, 1fr));
		grid-auto-rows: minmax(148px, 1fr);
		gap: 10px;
		align-content: start;
	}

	.div5.centpercent {
		flex: 1 1 100%;
		width: 100%;
		max-width: 100%;
	}

	div.wrapper,
	div.wrapper2 {
		float: none !important;
		width: auto !important;
		height: auto !important;
		min-height: 0;
		margin: 0;
		padding: 0;
		border: 1px solid #e4e7ec;
		border-radius: 10px;
		box-sizing: border-box;
		background: #fff;
		position: relative;
		box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
		overflow: hidden;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		cursor: pointer;
	}

	.div4 .wrapper.divempty,
	.div5 .wrapper2.divempty {
		display: none !important;
	}

	.div4 .wrapper:nth-last-child(1),
	.div4 .wrapper:nth-last-child(2),
	.div5 .wrapper2.arrow {
		display: flex !important;
		min-height: 86px;
	}

	.div4 .wrapper:hover,
	.div5 .wrapper2:hover {
		box-shadow: 0 3px 9px rgba(15, 23, 42, 0.14);
		transform: translateY(-1px);
	}

	.div4 img.imgwrapper,
	.div5 img.imgwrapper {
		display: block;
		width: 100%;
		height: calc(100% - 42px);
		max-width: 100%;
		max-height: none;
		object-fit: contain;
		padding: 8px 8px 2px;
		box-sizing: border-box;
		flex: 1 1 auto;
	}

	.div4 div.description,
	.div5 div.description {
		position: static;
		bottom: auto;
		left: auto;
		width: 100%;
		min-height: 42px;
		padding-top: 0;
		background: rgba(255, 255, 255, 0.96);
		opacity: 1;
		display: flex;
		align-items: center;
		justify-content: center;
		flex: 0 0 auto;
		box-sizing: border-box;
	}

	.div4 div.description_content,
	.div5 div.description_content {
		font-size: 12px;
		line-height: 1.25;
		font-weight: 600;
		padding: 4px 6px;
		max-height: 42px;
		width: 100%;
		box-sizing: border-box;
		-webkit-line-clamp: 2;
	}

	.div5 .productprice {
		font-size: 12px;
		line-height: 1.1;
		top: 6px;
		right: 6px;
		padding: 3px 6px;
		border-radius: 6px;
		z-index: 3;
		white-space: nowrap;
	}

	[dir="rtl"] .div5 .productprice,
	body.tp-rtl .div5 .productprice {
		right: auto;
		left: 6px;
	}

	.div4 .catwatermark,
	.div5 .catwatermark {
		font-size: 12px;
		border-radius: 6px;
		padding: 2px 4px;
	}

	.div5 button.productbutton {
		width: 100% !important;
		height: 100% !important;
		border-radius: 10px;
		font-size: 13px;
		padding: 8px;
	}
}


/* 2026-04-19 layout fix v2: compact, consistent POS catalog layout.
 * Goal: avoid oversized/empty product cards, give products more vertical space,
 * and show search results full-width without the category panel stealing space.
 */
@media screen and (min-width: 768px) {
	.bodytakepos #takepos-main-layout {
		height: calc(100vh - 52px);
		min-height: 0;
		overflow: hidden;
		box-sizing: border-box;
	}

	.row1withhead {
		height: 32% !important;
		min-height: 175px;
		max-height: 300px;
		overflow: hidden;
	}

	.row2withhead {
		height: 68% !important;
		min-height: 360px;
		overflow: hidden !important;
	}

	.div1,
	.div2,
	.div3 {
		height: 100%;
		box-sizing: border-box;
	}

	.div2 {
		display: flex;
		flex-wrap: wrap;
		align-content: stretch;
		gap: 2px;
		padding: 2px 5px;
	}

	.div2 .calcbutton,
	.div2 .calcbutton2 {
		width: calc(25% - 2px) !important;
		height: calc(25% - 2px) !important;
		margin: 0 !important;
	}

	.div3 {
		display: grid !important;
		grid-template-columns: repeat(3, minmax(0, 1fr));
		grid-auto-rows: minmax(40px, 1fr);
		gap: 2px;
		padding: 2px 8px 2px 5px;
		overflow-y: auto;
	}

	.div3 button.actionbutton {
		width: 100% !important;
		height: auto !important;
		min-height: 0;
		margin: 0 !important;
		padding: 5px 4px;
		font-size: 12px;
		line-height: 1.15;
		border: 1px solid #eee;
	}

	.row2,
	.row2withhead {
		display: flex !important;
		align-items: stretch;
		gap: 8px;
		padding: 8px;
		box-sizing: border-box;
		background: #f5f6f8;
	}

	.div4 {
		flex: 0 0 clamp(230px, 18vw, 420px) !important;
		width: clamp(230px, 18vw, 420px) !important;
		max-width: 420px !important;
		height: 100% !important;
		padding: 0 !important;
		float: none !important;
		display: grid !important;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		grid-auto-rows: 58px;
		align-content: start;
		gap: 8px;
		overflow-y: auto;
		overflow-x: hidden;
	}

	.div5 {
		flex: 1 1 auto !important;
		width: auto !important;
		max-width: none !important;
		height: 100% !important;
		padding: 0 !important;
		float: none !important;
		display: grid !important;
		grid-template-columns: repeat(auto-fill, minmax(150px, 170px));
		grid-auto-rows: 220px;
		justify-content: start;
		align-content: start;
		gap: 10px;
		overflow-y: auto;
		overflow-x: hidden;
	}

	.div5.centpercent {
		flex-basis: 100% !important;
		width: 100% !important;
		max-width: 100% !important;
		grid-template-columns: repeat(auto-fill, minmax(150px, 170px));
	}

	body.takepos-search-active .div4:not([style*="display: none"]) {
		display: grid !important;
	}

	body.takepos-search-active .div5:not(.centpercent) {
		flex: 1 1 auto !important;
		flex-basis: auto !important;
		width: auto !important;
		max-width: none !important;
	}

	.div4 div.wrapper,
	.div5 div.wrapper2 {
		float: none !important;
		width: 100% !important;
		height: 100% !important;
		min-height: 0 !important;
		margin: 0 !important;
		padding: 0 !important;
		border: 1px solid #e0e5ec !important;
		border-radius: 10px !important;
		box-sizing: border-box;
		background: #fff;
		box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
		overflow: hidden;
		display: flex;
		flex-direction: column;
		align-items: stretch;
		justify-content: flex-start;
	}

	.div4 div.wrapper {
		justify-content: center;
		background: #ffffff;
	}

	.div4 img.imgwrapper {
		display: none !important;
	}

	.div4 div.description {
		position: static !important;
		width: 100% !important;
		height: 100% !important;
		min-height: 0 !important;
		padding: 0 !important;
		background: transparent !important;
		display: flex !important;
		align-items: center;
		justify-content: center;
	}

	.div4 div.description_content {
		font-size: 13px;
		font-weight: 700;
		line-height: 1.2;
		padding: 6px 8px;
		max-height: none;
		-webkit-line-clamp: 2;
		text-transform: uppercase;
	}

	.div5 div.wrapper2.divempty,
	.div5 div.wrapper2.arrow.takepos-page-disabled {
		display: none !important;
	}

	.div5 div.wrapper2.arrow {
		align-items: center;
		justify-content: center;
		background: #fff;
		color: #263851;
	}

	.div5 img.imgwrapper {
		display: block;
		width: 100% !important;
		height: 154px !important;
		max-width: 100% !important;
		max-height: 154px !important;
		object-fit: contain;
		box-sizing: border-box;
		padding: 8px 8px 4px;
		flex: 0 0 154px;
	}

	.div5 div.description {
		position: static !important;
		width: 100% !important;
		min-height: 54px !important;
		height: 54px !important;
		padding: 0 !important;
		background: #fff !important;
		display: flex !important;
		align-items: flex-start;
		justify-content: center;
		box-sizing: border-box;
		border-top: 1px solid #eef1f5;
	}

	.div5 div.description_content {
		font-size: 12px;
		font-weight: 700;
		line-height: 1.25;
		padding: 6px 7px 4px;
		max-height: 54px;
		width: 100%;
		box-sizing: border-box;
		overflow: hidden;
		display: -webkit-box !important;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 3;
		word-break: break-word;
	}

	.div5 .productprice {
		position: absolute;
		top: 6px;
		right: 6px;
		font-size: 11px !important;
		line-height: 1.1;
		padding: 4px 7px;
		border-radius: 7px;
		z-index: 3;
		white-space: nowrap;
		max-width: calc(100% - 12px);
		overflow: hidden;
		text-overflow: ellipsis;
	}

	[dir="rtl"] .div5 .productprice,
	body.tp-rtl .div5 .productprice {
		right: auto;
		left: 6px;
	}

	.div4 .catwatermark,
	.div5 .catwatermark {
		display: none !important;
	}

	.div5 button.productbutton {
		width: 100% !important;
		height: 100% !important;
		border-radius: 10px;
		font-size: 13px;
		padding: 10px;
	}
}

@media screen and (min-width: 1400px) {
	.div5 {
		grid-template-columns: repeat(auto-fill, minmax(155px, 180px));
		grid-auto-rows: 232px;
	}
	.div5 img.imgwrapper {
		height: 164px !important;
		max-height: 164px !important;
		flex-basis: 164px;
	}
}

@media screen and (max-width: 1100px) and (min-width: 768px) {
	.row1withhead {
		height: 36% !important;
	}
	.row2withhead {
		height: 64% !important;
	}
	.div4 {
		flex-basis: 200px !important;
		width: 200px !important;
		grid-template-columns: 1fr;
	}
	.div5 {
		grid-template-columns: repeat(auto-fill, minmax(135px, 1fr));
		grid-auto-rows: 205px;
	}
	.div5 img.imgwrapper {
		height: 140px !important;
		max-height: 140px !important;
		flex-basis: 140px;
	}
}
