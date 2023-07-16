<?php

$finder = PhpCsFixer\Finder::create()
  ->exclude('files')
  ->exclude('var')
  ->exclude('tools')
  ->exclude('views')
  ->notPath('config.example.php')
  ->notPath('config.php')
  ->in(__DIR__)
;

$header = <<<'EOT'
Snail Paste Release Manager: Tool to manage and track software project releases
Copyright (C) 2023  Snail Paste, LLC

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.
EOT;

$config = new PhpCsFixer\Config();
return $config->setRules([
  '@PSR12' => true,
  'declare_strict_types' => true,
  'strict_param' => true,
  'ordered_imports' => true,
  'array_syntax' => ['syntax' => 'short'],
  'header_comment' => [ 'header' => $header ]
])
  ->setIndent("  ")
  ->setFinder($finder)
  ;