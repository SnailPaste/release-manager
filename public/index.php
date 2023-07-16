<?php

declare(strict_types=1);

/*
 * Snail Paste Release Manager: Tool to manage and track software project releases
 * Copyright (C) 2023  Snail Paste, LLC
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

require(dirname(__FILE__, 2).'/vendor/autoload.php');

use App\Controller\AdminController;
use App\Controller\FileController;
use App\Database\DatabaseInterface;
use DI\Container;
use League\Config\Configuration;
use Nette\Schema\Expect;
use Psr\Container\ContainerInterface;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

$container = new Container();

$container->set(Configuration::class, function () {
  $config = new Configuration([
    // The directory that contains the software release assets to be served
    'files_root' => Expect::string()->default(dirname(__FILE__, 2).'/files'),
    'database' => Expect::structure([
      'driver' => Expect::anyOf('sqlite3')->default('sqlite3')
    ])
  ]);

  if (file_exists(dirname(__FILE__, 2).'/config.php')) {
    $config->merge(require(dirname(__FILE__, 2) . '/config.php'));
  }

  return $config;
});

$container->set(DatabaseInterface::class, function (Configuration $config) {
  $driver = $config->get('database.driver');
  if ($driver == 'sqlite3') {
    return new \App\Database\SQLite3([
      'path' => dirname(__FILE__, 2).'/var/data/data.sqlite3'
    ]);
  }

  return null;
});

$app = AppFactory::createFromContainer($container);

// Add Twig to Slim
$twig = Twig::create(dirname(__FILE__, 2).'/views', [
  'cache' => dirname(__FILE__, 2).'/var/cache/twig'
]);
$app->add(TwigMiddleware::create($app, $twig));

// Define routes
$app->group('/admin', function (RouteCollectorProxy $group) {
  $group->get('[/]', [AdminController::class, 'index']);
});

$app->get('{filepath:.+}/stats/json', [FileController::class, 'statsJSON']);
$app->get('{filepath:.+}/stats', [FileController::class, 'stats']);
$app->get('{filepath:.+}', [FileController::class, 'download']);

// Let's go!
$app->run();
