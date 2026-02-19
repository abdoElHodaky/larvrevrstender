<?php return array (
  'nesbot/carbon' => 
  array (
    'providers' => 
    array (
      0 => 'Carbon\\Laravel\\ServiceProvider',
    ),
  ),
  'nunomaduro/termwind' => 
  array (
    'providers' => 
    array (
      0 => 'Termwind\\Laravel\\TermwindServiceProvider',
    ),
  ),
  'sajya/server' => 
  array (
    'aliases' => 
    array (
      'RPC' => 'Sajya\\Server\\Facades\\RPC',
    ),
    'providers' => 
    array (
      0 => 'Sajya\\Server\\ServerServiceProvider',
    ),
  ),
);