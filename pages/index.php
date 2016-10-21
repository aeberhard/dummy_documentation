<?php
// Addon/Plugin-Informationen
$addon = rex_be_controller::getCurrentPagePart(1);
$docplugin = rex_be_controller::getCurrentPagePart(2);
$plugin = rex_plugin::get($addon, $docplugin);

//$lang = rex::getProperty('lang'); // System-Backend-Sprache
$lang = rex::getUser()->getLanguage(); // User-Backend-Sprache
// Sprache der Dokumentation aus package.yml
if ($plugin->getProperty('documentationlang')) {
    $lang = $plugin->getProperty('documentationlang');
}

// Pfad zusammenbauen aus Addon + Plugin
$path = rex_path::plugin($addon, $docplugin , 'docs/' . $lang . '/');

// Default Navigation aus package.yml
$default_navi = $plugin->getProperty('defaultnavi');
if (!$default_navi) {
    $default_navi = 'main_navi.md';
}

// Default Intro aus package.yml
$default_intro = $plugin->getProperty('defaultintro');
if (!$default_intro) {
    $default_intro = 'main_intro.md';
}

// vorhandene Dateien ermitteln
$files = [];
if (file_exists($path) && is_dir($path)) {
    foreach (scandir($path) as $i_file) {
        if ($i_file != '.' && $i_file != '..') {
            $files[$i_file] = $i_file;
        }
    }
}

// Bild ausgeben wenn Parameter document_image gesetzt ist und die Datei existiert
if (rex_request('document_image', 'string', '') != '' && isset($files[rex_request('document_image', 'string')])) {
    while (ob_get_length()) {
        ob_end_clean();
    }
    $content = rex_file::get($path . basename(rex_request('document_image', 'string')));
    echo $content;
    exit;
}

// Navigation aus $default_navi
$navi = trim(rex_file::get($path . $default_navi));
if ($navi == '') {
    $navi = rex_view::error(rex_i18n::rawMsg('documentation_navinotfound', $lang . '/' . $default_navi));
}

// Content aus Parameter document_file, sonst aus $default_intro
$file = rex_request('document_file', 'string', $default_intro);
$content = trim(rex_file::get($path . basename($file)));
if ($content == '') {
    $content = rex_view::warning(rex_i18n::rawMsg('documentation_filenotfound', $lang . '/' . $file, $this->getProperty('supportpage')));
}

// Images im Inhalt ersetzen
// ![Alt-Text](bildname.png)
// ![Ein Screenshot](screenshot.png)
foreach ($files as $i_file) {
    $search = '#\!\[(.*)\]\((' . $i_file . ')\)#';
    $replace = '<img src="index.php?page='. $addon . '/' . $docplugin . '&document_image=$2" alt="$1" title="$1" style="width:100%" />';
    $content = preg_replace($search, $replace, $content);
}

// Parse Navigation & Content
if (class_exists('rex_markdown')) {
    $parser = rex_markdown::factory();
    $navi = $parser->parse($navi);
    $content = $parser->parse($content);
} else if (class_exists('Parsedown')) {
    $parser = new Parsedown();
    $navi = $parser->text($navi);
    $content = $parser->text($content);
} else {
    $navi = '';
    $content = rex_view::error(rex_i18n::rawMsg('documentation_noparser'));
}

// Links in Navigation ersetzen
$search = '#<li><a href="#';
$replace = '<li><a href="index.php?page=' . $addon . '/' . $docplugin . '&document_file=';
$navi = preg_replace($search, $replace, $navi);
$navi = str_replace('document_file=' . $file .'"', 'document_file=' . $file .'" class="current"', $navi);

// Links im Inhalt ersetzen
foreach ($files as $i_file) {
    $search = '#href="(' . $i_file . ')"#';
    $replace = 'href="index.php?page=' . $addon . '/' . $docplugin . '&document_file=$1"';
    $content = preg_replace($search, $replace, $content);
}

// Navigation
$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::rawMsg('documentation_navigation_title'), false);
$fragment->setVar('body', $navi, false);
$navi = $fragment->parse('core/page/section.php');

// Inhalt
$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::rawMsg('documentation_content_title'), false);
$fragment->setVar('body', $content, false);
$content = $fragment->parse('core/page/section.php');

// Navigation und Inhalt ausgeben
echo '
<section class="addon_documentation">
    <div class="row">
        <div class="col-md-4 addon_documentation-navi">' . $navi . '
        </div>
        <div class="col-md-8 addon_documentation-content">' . $content . '
        </div>
    </div>
</section>

';
