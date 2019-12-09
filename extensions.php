<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Firefox Extension Search (Beta)</title>
<style type="text/css">
body {margin: 0 10px; font-family: sans-serif;}
table {border-right: 1px solid #888; border-bottom: 1px solid #888; width: 100%; table-layout: fixed;}
th, td {text-align: left; padding: 8px 12px; border-top: 1px solid #888; border-left: 1px solid #888; vertical-align: top;}
td p {margin: 0 0 6px; }
.hostslist {max-height: 6em; overflow: hidden; transition: all 250ms ease-in-out 750ms;}
.hostslist:hover {max-height: 99em; overflow: hidden;}
td:nth-of-type(1) {padding-left: 48px;}
td:nth-of-type(1) img.icon {float:left; margin-left: -40px; width:32px; height:32px}
#navigateresults {text-align:center; background:#def;}
#querylink {font-size: 0.8em;}
.uncompat {color: #f0f0f0; background-color: #f00;}
.guid {opacity: 0.8; font-size: 0.75em;}
.guid:hover {opacity: 1;}
#linktooltip {position:absolute; top:1em; left:0; z-index:10; margin:2px; border:1px solid black; padding:3px; background: #ffc; display:none;}
.linkSpan {position:relative; text-decoration:underline; color:blue; cursor:pointer;}
.linkSpan[hastooltip] {background:#ff0; color:black;}
.recom {background-color: #f0fff0;}
</style>
</head>
<body>
<h1>Firefox Extension Search (Beta)</h1>
<form action="#" onsubmit="getExtensions(); return false;">
	<p>Text to match: <input type="text" id="txtQuery" size="60" autofocus> <select id="selType">
		<option value="q" selected>Text</option>
		<option value="guid">GUID</option>
	</select> <em>Order by:</em> <select id="sort">
		<option value="relevance" selected>Relevance</option>
		<option value="updated">Last Updated</option>
		<option value="created">Recently Created</option>
		<option value="recommended|rating">Recommended/Rating</option>
	</select> <input type="submit" id="btnGo" value="Search"> (Live AMO Lookup)
	<span id="querylink"></span></p>
</form>
<table id="extensions" cellspacing="0" cellpadding="0">
	<thead><tr>
		<th style="width:65%">Name, Summary &amp; Stats</th>
		<th style="width:35%">Permissions</th>
	</tr></thead>
	<tbody>
		<tr id="retrieving"><td colspan="4">Waiting to run a search...</td></tr>
	</tbody>
</table>
<p id="navigateresults"></p>
<noscript><p>JavaScript is required for this page!</p></noscript>
<p id="copy">Version 1.6 (September 26, 2019). Copyright &copy; 2019 Jefferson "jscher2000" Scher. License: <a href="https://www.mozilla.org/MPL/2.0/">MPL 2.0</a></p>
<p id="linktooltip"></p>
<script type="text/javascript">
var userlang = navigator.languages; // array
function getExtensions(url){
	var txt = document.getElementById('txtQuery').value.trim();
	if (txt.length < 1){
		if (document.getElementById('sort').value == 'relevance'){
			alert('Try a longer query!'); return false;
		} else {
			var qry = '';
		}
	} else {
		if (document.getElementById('selType').value === 'guid' && txt.indexOf('@') < 0){ // add {} if needed
			if (txt.slice(0,1) !== '{') txt = '{' + txt;
			if (txt.slice(-1) !== '}') txt += '}';
		}
		var qry = document.getElementById('selType').value + '=' + encodeURIComponent(txt);
	}
	if (url === undefined || url.length == 0){
		url = 'https://addons.mozilla.org/api/v4/addons/search/?app=firefox&amp;type=extension&amp;sort=' +  document.getElementById('sort').value.replace('|', ',') + '&amp;' + qry;
		// Clear existing table contents
		var rows = document.querySelectorAll('#extensions tbody tr');
		for (var i=rows.length; i>0; i--) rows[i-1].remove();
		// Creates permalink
		if (qry !== ''){
			if (document.getElementById('sort').value !== 'relevance'){
				document.getElementById('querylink').innerHTML = '<br>Link for sharing: ' +
					'<a href="https://www.jeffersonscher.com/sumo/extensions.php#sort=' + document.getElementById('sort').value + ',' + qry + 
					'">https://www.jeffersonscher.com/sumo/extensions.php#sort=' + document.getElementById('sort').value + ',' + qry + '</a>';
			} else {
				document.getElementById('querylink').innerHTML = '<br>Link for sharing: ' +
					'<a href="https://www.jeffersonscher.com/sumo/extensions.php#' + qry + 
					'">https://www.jeffersonscher.com/sumo/extensions.php#' + qry + '</a>';
			}
		} else {
			if (document.getElementById('sort').value !== 'relevance'){
				document.getElementById('querylink').innerHTML = '<br>Link for sharing: ' +
					'<a href="https://www.jeffersonscher.com/sumo/extensions.php#sort=' + document.getElementById('sort').value + 
					'">https://www.jeffersonscher.com/sumo/extensions.php#sort=' + document.getElementById('sort').value + '</a>';
			} else {
				document.getElementById('querylink').innerHTML = '';
			}			
		}
	} 
	var request = new XMLHttpRequest();
	request.onreadystatechange = function() {
		if (request.readyState==4) {
		  if (request.status==200) {
			var respObj = JSON.parse(request.responseText);
			if (respObj.errdesc){
				alert(respObj.errdesc);
				return false;
			}
			tableize(respObj);
			request = null;
			return false;
		  }
		}
	}
	request.open("get", url, true);
	request.send();
}
function tableize(v4obj){
	var arrExtensions = v4obj.results;
	var tgt = document.querySelector('#extensions tbody');
	var oName, oSumm, sName, sSumm, arrAuthors, sAuthors, sCompat, sMin, oFiles, oFile, sPerms, sAllHosts, arrHosts, sHost, arrHostparts, arrAPIs1, arrAPIs2, sStats, sRowTag;
	for (var i=0; i<arrExtensions.length; i++){
		// Extract name and summary
		oName = arrExtensions[i].name;
		oSumm = arrExtensions[i].summary;
		sName = '';
		if (Object.keys(oName).length === 1){
			// If only one name, use that
			sName = oName[Object.keys(oName)[0]];
		} else {
			// Try to find a name in the user's acceptable languages
			for (var j=0; j<userlang.length; j++){
				if (oName[userlang[j]] !== undefined) {
					sName = oName[userlang[j]];
					break;
				}
			}
			if (sName == ''){
				if (oName['en-US'] !== undefined) {
					// Check for U.S. English
					sName = oName['en-US'];
				} else {
					// Just use the first one for now
					sName = oName[Object.keys(oName)[0]];
				}
			}
		}
		sSumm = '';
		if (Object.keys(oSumm).length === 1){
			// If only one summary, use that
			sSumm = oSumm[Object.keys(oSumm)[0]];
		}  else {
			// Try to find a name in the user's acceptable languages
			for (j=0; j<userlang.length; j++){
				if (oSumm[userlang[j]] !== undefined) {
					sSumm = oSumm[userlang[j]];
					break;
				}
			}
			if (sSumm == ''){
				if (oSumm['en-US'] !== undefined) {
					// Check for U.S. English
					sSumm = oSumm['en-US'];
				} else {
					// Just use the first one for now
					sSumm = oSumm[Object.keys(oSumm)[0]];
				}
			}
		}
		// Modify summary tags and link behavior
		sSumm = sSumm.replace(/<a/g, '{[{span class="linkSpan"').replace(/<\/a>/g, '{[{/span}]}');
		sSumm = sSumm.replace(/</g, '&lt;');
		if (sSumm.indexOf('{[{span') > -1){
			var arrSumm = sSumm.split('{[{span');
			for (j=1; j<arrSumm.length; j++){
				var gtPos = arrSumm[j].indexOf('>');
				arrSumm[j] = arrSumm[j].substr(0, gtPos) + ' onclick="linkTip(this);"}]}' + arrSumm[j].substr(gtPos + 1);
			}
			sSumm = arrSumm.join('<span');
			sSumm = sSumm.replace(/\{\[\{\/span/g, '</span');
			sSumm = sSumm.replace(/>/g, '&gt;');
			sSumm = sSumm.replace(/\}\]}/g, '>')
		} else {
			sSumm = sSumm.replace(/>/g, '&gt;');
		}
		// Extract authors
		arrAuthors = arrExtensions[i].authors;
		sAuthors = '';
		for (var k=0; k<arrAuthors.length; k++){
			sAuthors += ', <a href="' + arrAuthors[k].url + '" target="_blank" rel="noopener">' + arrAuthors[k].name + '</a>';
		}
		// Compat warnings
		if (isNaN(FxVer) === false){
			sMin = arrExtensions[i].current_version.compatibility.firefox.min;
			if (sMin.indexOf('a') > -1) sMin = sMin.substr(0, sMin.indexOf('a'));
			if((isNaN(sMin) || (FxVer >= sMin)) &&
				(isNaN(arrExtensions[i].current_version.compatibility.firefox.max) || 
				(FxVer <= arrExtensions[i].current_version.compatibility.firefox.max))){
					sCompat = ' <span class="compat">';
				} else {
					sCompat = ' <span class="uncompat">';
				}
		} else {
			sCompat = ' <span class="compat">';
		}
		sCompat += '(Fx&nbsp;' + arrExtensions[i].current_version.compatibility.firefox.min + '&nbsp;-&nbsp;' + 
					arrExtensions[i].current_version.compatibility.firefox.max + ')</span>'
		// Build name, summary, ratings text
		if (document.getElementById('sort').value == 'updated'){
			var updated = ' <strong>updated ' + new Date(arrExtensions[i].last_updated).toLocaleDateString() + '</strong>';
		} else {
			updated = ' updated ' + new Date(arrExtensions[i].last_updated).toLocaleDateString();
		}
		var created = '';
		if (document.getElementById('sort').value == 'created'){
			created = ' <strong> - created ' + new Date(arrExtensions[i].created).toLocaleDateString() + '</strong>';
		}
		sName = '<p><img class="icon" src="' + arrExtensions[i].icon_url + '"><a href="' + arrExtensions[i].url + 
				'" target="_blank" rel="noopener">' + sName.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</a> ' +
				' by ' + sAuthors.substr(1) + '<span class="guid"> - ' + arrExtensions[i].guid + '</span>' + 
				'<br>Version ' + arrExtensions[i].current_version.version + updated + sCompat + created + '</p>\n' +
				'<p>' + sSumm + '</p>';
		oFiles = arrExtensions[i].current_version.files;
		sPerms = '';
		for (var ndx in oFiles) {
			oFile = oFiles[ndx];
			arrHosts = [];
			arrAPIs1 = [];
			arrAPIs2 = [];
			sAllHosts = '';
			for (var j=0; j<oFile.permissions.length; j++){
				if ((oFile.permissions[j] == '<all_urls>' || oFile.permissions[j].indexOf('://*/') > -1)) {
					if (sAllHosts == '') sAllHosts = '<p><b style="background:#ff8;">Access your data for all websites</b></p>\n';
				} else {
					if (oFile.permissions[j].indexOf('/') > -1){
						sHost = oFile.permissions[j].replace(/</g, '&lt;').replace(/>/g, '&gt;');
						sHost = sHost.replace(/:\/\/([\d\w\.\-\*]*)\//, '://<b>$1</b>/');
						if (!arrHosts.includes(sHost)) arrHosts.push(sHost);
					} else {
						// TODO: parse for interesting ones
						if (permDesc[oFile.permissions[j]] !== undefined){
							if (!arrAPIs1.includes(permDesc[oFile.permissions[j]])) arrAPIs1.push(permDesc[oFile.permissions[j]]);
						} else {
							if (!arrAPIs2.includes(oFile.permissions[j].replace(/</g, '&lt;').replace(/>/g, '&gt;'))) arrAPIs2.push(oFile.permissions[j].replace(/</g, '&lt;').replace(/>/g, '&gt;'));
						}
					}
				}
			}
		}
		sPerms += sAllHosts;
		if (arrHosts.length > 0){
			arrHosts.sort();
			sPerms += '<p class="hostslist">' + 'Access your data for: ' + arrHosts.join(', ') + '</p>\n';
		}
		if (arrAPIs1.length > 0){
			sPerms += '<p><b>' + arrAPIs1.join('</b></p><p><b>') + '</b></p>\n';
		}
		if (arrAPIs2.length > 0){
			arrAPIs2.sort();
			sPerms += '<p><i>' + 'Other API permissions:</i> ' + arrAPIs2.join(', ') + '</p>\n';
		}
		// Stats
		sStats = '<p>' + arrExtensions[i]['average_daily_users'].toLocaleString() + ' users &bull; ';
		if (arrExtensions[i].ratings.count > 0) {
			sStats += arrExtensions[i].ratings.count + ' ratings: <strong>' + 
				Number.parseFloat(Math.round(arrExtensions[i].ratings.average * 10)/10).toFixed(1) + '</strong> / 5.0';
			if (arrExtensions[i].ratings.text_count > 0){
				sStats += ' &bull; <a href="' + arrExtensions[i].ratings_url + '" target="_blank" rel="noopener">' + 
					arrExtensions[i]['ratings']['text_count'].toLocaleString() + ' written review(s)</a>';
				if (arrExtensions[i].is_recommended){
					sStats += ' &bull; <strong>Recommended</strong></p>';
				} else {
					sStats += '</p>';
				}
			} else {
				sStats += ' &bull; No written reviews.</p>';
			}
		} else {
			sStats += ' Not yet rated or reviewed.</p>'
		}
		if (arrExtensions[i].current_version.reviewed != null){
			sStats += '\n<p><em>Code review of version</em> ' + arrExtensions[i].current_version.version + 
				' <em>on</em> ' + new Date(arrExtensions[i].current_version.reviewed).toLocaleString() + '</p>\n'
		}
		if (arrExtensions[i].is_recommended) sRowTag = '<tr class="recom">'
		else sRowTag = '<tr>';
		tgt.insertAdjacentHTML('beforeend', sRowTag + '<td>' + sName + sStats + '</td><td>' + sPerms + '</td></tr>');
	}
	// Retrieve further records
	if (v4obj.next){
		var nav = '<a href="' + v4obj.next + '" onclick="getExtensions(this.href); return false;">Retrieve More Results</a>' + 
			' &bull; <a href="#" onclick="document.documentElement.scrollTop = 0; return false;">Return to top</a>';
	} else {
		var nav = 'No More Results &bull; <a href="#" onclick="document.documentElement.scrollTop = 0; return false;">Return to top</a>';
	}
	document.getElementById('navigateresults').innerHTML = nav;
}
// From https://github.com/mozilla/gecko/blob/central/browser/locales/en-US/chrome/browser/browser.properties
permDesc = {
	"bookmarks": "Read and modify bookmarks",
	"browserSettings": "Read and modify browser settings",
	"browsingData": "Clear recent browsing history, cookies, and related data",
	"clipboardRead": "Get data from the clipboard",
	"clipboardWrite": "Input data to the clipboard",
	"devtools": "Extend developer tools to access your data in open tabs",
	"dns": "Access IP address and hostname information",
	"downloads": "Download files and read and modify the browser’s download history",
	"downloads.open": "Open files downloaded to your computer",
	"find": "Read the text of all open tabs",
	"geolocation": "Access your location",
	"history": "Access browsing history",
	"management": "Monitor extension usage and manage themes",
	"nativeMessaging": "Exchange messages with programs other than Firefox",
	"notifications": "Display notifications to you",
	"pkcs11": "Provide cryptographic authentication services",
	"privacy": "Read and modify privacy settings",
	"proxy": "Control browser proxy settings",
	"sessions": "Access recently closed tabs",
	"tabs": "Access browser tabs",
	"tabHide": "Hide and show browser tabs",
	"topSites": "Access browsing history",
	"unlimitedStorage": "Store unlimited amount of client-side data",
	"webNavigation": "Access browser activity during navigation"
}
<?php
// Legacy permalinks
if (isset($_GET) && count($_GET) > 0){
	$qry = urldecode(trim($_GET["q"]));
	if (strlen($qry) > 0){
		echo "// search the passed query string" . PHP_EOL;
		echo "document.getElementById('txtQuery').value = '" . $qry . "';" . PHP_EOL;
		if (strlen($qry) > 2){
			echo "document.getElementById('btnGo').click();" . PHP_EOL;
		}
	}
}
?>
// check for a hash
if (location.hash !== '' && location.hash !== '#'){
	var hs = location.hash.split('='); 
	if (hs.length > 1 && document.getElementById('txtQuery').value == ''){
		switch (hs[0]){
			case '#q':
				document.getElementById('selType').value = 'q';
				document.getElementById('txtQuery').value = decodeURIComponent(hs[1]);
				document.getElementById('btnGo').click();
				break;
			case '#guid':
				document.getElementById('selType').value = 'guid';
				document.getElementById('txtQuery').value = decodeURIComponent(hs[1]);
				document.getElementById('btnGo').click();
				break;
			case '#sort':
				var hashparts = hs[1].split(',');
				document.getElementById('sort').value = hashparts[0];
				if (hashparts.length > 1){
					var pastcomma = location.hash.substr(location.hash.indexOf(',') + 1).split('=');
					if ((pastcomma[0] == 'q' || pastcomma[0] == 'guid') && pastcomma.length > 1){
						document.getElementById('selType').value = pastcomma[0];
						document.getElementById('txtQuery').value = decodeURIComponent(pastcomma[1]);
					}
				}
				document.getElementById('btnGo').click();
				break;
			default:
				// no-op
		}
	}
}
// Store browser version
var FxVer = navigator.userAgent;
if (FxVer.indexOf('Firefox/')){
	FxVer = FxVer.substr(FxVer.indexOf('Firefox/') + 8).trim();
	if (FxVer.indexOf(' ') > -1) FxVer = FxVer.substr(0, FxVer.indexOf(' '));
} else {
	FxVer = '*';
}
// Special handling of summary links
var tooltip = document.getElementById('linktooltip');
function linkTip(el){
	if (!el.hasAttribute('hastooltip')){
		tooltip.innerHTML = '<a href="' + el.getAttribute('href') + '" target="_blank" onclick="window.setTimeout(clearTip, 100); return true;">' + el.getAttribute('href') + '</a>';
		el.appendChild(tooltip);
		el.setAttribute('hastooltip', 'true');
		tooltip.style.display = 'block';
	} else {
		window.setTimeout(clearTip, 100);
		return true;
	}
}
function clearTip(evt){
	tooltip.style.display = '';
	tooltip.parentNode.removeAttribute('hastooltip');
}
</script>
</body>
</html>
