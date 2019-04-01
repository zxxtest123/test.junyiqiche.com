<?php

return array (
  'autoload' => false,
  'hooks' => 
  array (
    'user_sidenav_after' => 
    array (
      0 => 'cms',
    ),
    'upload_config_init' => 
    array (
      0 => 'upyun',
    ),
  ),
  'route' => 
  array (
    '/cms/$' => 'cms/index/index',
    '/cms/a/[:diyname]' => 'cms/archives/index',
    '/cms/t/[:name]' => 'cms/tags/index',
    '/cms/p/[:diyname]' => 'cms/page/index',
    '/cms/s' => 'cms/search/index',
    '/cms/c/[:diyname]' => 'cms/channel/index',
    '/cms/d/[:diyname]' => 'cms/diyform/index',
    '/third$' => 'third/index/index',
    '/third/connect/[:platform]' => 'third/index/connect',
    '/third/callback/[:platform]' => 'third/index/callback',
  ),
);