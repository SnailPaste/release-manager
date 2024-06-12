<?php

declare(strict_types=1);

/*
 * Snail Paste Release Manager: Tool to manage and track software project releases
 * Copyright (C) 2023-2024  Snail Paste, LLC
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

require(dirname(__DIR__).'/vendor/autoload.php');

use App\Controller\AdminController;
use App\Controller\FileController;
use App\Controller\IndexController;
use App\Database\DatabaseInterface;
use DI\Bridge\Slim\Bridge;
use DI\Container;
use League\Config\Configuration;
use Nette\Schema\Expect;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

$container = new Container();

$container->set(Configuration::class, function () {
  $config = new Configuration([
    // The filesystem path that contains the software release assets to be served
    'files_root' => Expect::string()->default(dirname(__DIR__).'/files'),
    'site_title' => Expect::string()->required(),
  ]);

  $config->merge(require(dirname(__DIR__).'/config.php'));

  return $config;
});

$container->set(Twig::class, function (Configuration $config) {
  $twig = Twig::create(dirname(__DIR__).'/views', [
    'cache' => dirname(__DIR__).'/var/cache/twig',
    'auto_reload' => true
  ]);

  $twig->addExtension(new \App\Misc\TwigCommonMark());

  $twig->offsetSet('site_title', $config->get('site_title'));

  return $twig;
});

$container->set(DatabaseInterface::class, function (Configuration $config) {
  return new \App\Database\SQLite3([
    'path' => dirname(__DIR__).'/var/data/data.sqlite3'
  ]);
});

// Create the app
$app = Bridge::create($container);

// Fetch the configuration, which we'll need to get the files_root path
$config = $app->getContainer()->get(Configuration::class);

// Restrict the paths that PHP is allowed to read from to limit possible exploits
ini_set('open_basedir', join(PATH_SEPARATOR, [
  dirname(__DIR__),
  $config->get('files_root')
]));

// Add Twig middleware
$app->add(TwigMiddleware::createFromContainer($app, Twig::class));

// Define routes
$app->group('/admin', function (RouteCollectorProxy $group) {
  $group->get('', [\App\Controller\RedirectController::class, 'addTrailingSlash']);
  $group->get('/', [AdminController::class, 'index']);
});

$app->get('/{project}/{platform}/{version}/{filename}/info/json', [FileController::class, 'infoJSON'])->setName('infoJSON');
$app->get('/{project}/{platform}/{version}/{filename}/info', [FileController::class, 'info'])->setName('info');
$app->get('/{project}/{platform}/{version}/{filename}', [FileController::class, 'download'])->setName('download');

$app->get('/{project_slug}/', [IndexController::class, 'releases'])->setName('releases');
$app->get('/{project_slug}', [\App\Controller\RedirectController::class, 'addTrailingSlash']);
$app->get('/', [IndexController::class, 'projects'])->setName('projects');

// Let's go!
$app->run();
