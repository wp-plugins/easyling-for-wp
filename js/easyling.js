(function() {

    window.onload = function() {

        var floater = document.createElement('span');
        floater.setAttribute('class', 'easyling-floater');
        floater.setAttribute('id', 'easyling-floater');
        var size = 0;
	    var lineHeight = 22;
        for (var locale in easyling_languages.translations) {
            var child = document.createElement('a');
            child.setAttribute('href', easyling_languages.translations[locale].url);
            child.innerText = "&nbsp;";
	        if (easyling_languages.translations[locale].coords == null) {
		        child.innerHTML = locale;
		        child.style.color = "black";
		        child.style.top = 2 + lineHeight * size + "px";
		        child.style.textIndent = "0px";
		        child.style.lineHeight = lineHeight+"px";
	        } else {
	            child.style.top = 2 + lineHeight * size + "px";
	            child.style.backgroundImage = 'url(' + easyling_languages.baseurl + '/images/flags_32.png)';
	            child.style.backgroundPosition = easyling_languages.translations[locale].coords.x * -1 + "px " + (easyling_languages.translations[locale].coords.y + 5) * -1 + "px";
	        }
            floater.appendChild(child);
            size++;
        }
        floater.style.height = 4 + lineHeight * size + "px";
        document.body.appendChild(floater);
    }
})();