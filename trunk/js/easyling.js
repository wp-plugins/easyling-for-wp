(function() {

    window.onload = function() {

        var floater = document.createElement('span');
        floater.setAttribute('class', 'easyling-floater');
        floater.setAttribute('id', 'easyling-floater');
        var size = 0;
        for (var lang in easyling_languages.translations) {
            var child = document.createElement('a');
            child.setAttribute('href', easyling_languages.translations[lang].url);
            child.innterText = "&nbsp;";
            child.style.top = 2 + 22 * size + "px";
            child.style.backgroundImage = 'url(' + easyling_languages.baseurl + '/images/flags_32.png)';
            child.style.backgroundPosition = easyling_languages.translations[lang].coords.x * -1 + "px " + (easyling_languages.translations[lang].coords.y + 5) * -1 + "px";
            floater.appendChild(child);
            size++;
        }
        floater.style.height = 4 + 22 * size + "px";
        document.body.appendChild(floater);
    }
})();