<?php

array_push($globals['cache-control'], 'no-cache');
do_header(_("editar noticia"), "post");

echo '<div id="singlewrap">'."\n";

if (!empty($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
    $link = new Link;
    $link->id = intval($_REQUEST['id']);
    $link->read();

    if (!$link->is_editable() || intval($_GET['user']) != $current_user->user_id) {
        echo '<div class="form-error-submit">&nbsp;&nbsp;'._("noticia no modificable").'</div>'."\n";
    } else {
        if ($_POST) {
            require __DIR__.'/link-2-post.php';
        } else {
            do_edit($link);
        }
    }
} else {
    echo '<div class="form-error-submit">&nbsp;&nbsp;'._("¿duh?").'</div>';
}

echo "</div>"."\n";

function do_edit($link)
{
    global $dblang, $db, $current_user, $globals, $site_key;

    $link->status = $link->sub_status;
    $link->discarded = $link->is_discarded();
    $link->status_text = $link->get_status_text();
    $link->key = md5($link->randkey.$current_user->user_id.$current_user->user_email.$site_key.get_server_name());
    $link->has_thumb();
    $link->is_new = false;
    $link->is_sub_owner = SitesMgr::is_owner();

    $site_properties = SitesMgr::get_extended_properties();

    $link->chars_left = $site_properties['intro_max_len'] - mb_strlen(html_entity_decode($link->content, ENT_COMPAT, 'UTF-8'), 'UTF-8');

    if (empty($link->url)) {
        $link->poll = new Poll;
        $link->poll->read('link_id', $link->id);
    }

    Haanga::Load('story/edit/edit.html', compact('globals', 'link', 'site_properties'));
}
