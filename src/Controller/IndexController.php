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

namespace App\Controller;

use App\Database\DatabaseInterface;
use Composer\Semver\Comparator;
use League\Config\Configuration;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

class IndexController
{
  public function __construct(private DatabaseInterface $db, private Configuration $config, private Twig $twig)
  {

  }

  public function projects(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
  {
    $projects = $this->db->getProjects();

    return $this->twig->render($response, 'index/projects.html.twig', [
      'projects' => $projects,
    ]);
  }

  public function releases(ServerRequestInterface $request, ResponseInterface $response, string $project_slug): ResponseInterface
  {
    $project = $this->db->getProjectBySlug($project_slug);
    if ($project === null) {
      return $this->twig->render($response, 'error/404.html.twig', [
        'return_home' => true
      ])->withStatus(404);
    }
    $releases = $this->db->getReleasesByProjectSlug($project_slug);
    $files = $this->db->getFilesByProject($project['id']);

    // Sort the releases by semver
    usort($releases, function ($a, $b) {
      if (Comparator::equalTo($a['semver'], $b['semver'])) {
        return 0;
      } elseif (Comparator::lessThan($a['semver'], $b['semver'])) {
        return 1;
      } else {
        return -1;
      }
    });

    return $this->twig->render($response, 'index/releases.html.twig', [
      'project' => $project,
      'releases' => $releases,
      'files' => $files,
    ]);
  }
}
