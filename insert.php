<?php
$file = 'resources/js/views/owner/OwnerLayout.vue';
$content = file_get_contents($file);
$search = '<router-link to="/owner/pricing" class="nav-item" active-class="nav-active">';
$replace = '<router-link to="/owner/profile/application" class="nav-item" active-class="nav-active">
          <AppIcon name="fileText" size="18" />
          <span>Hồ sơ & Hợp đồng</span>
        </router-link>
        <router-link to="/owner/pricing" class="nav-item" active-class="nav-active">';
$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
