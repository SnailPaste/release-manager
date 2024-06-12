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
use cardinalby\ContentDisposition\ContentDisposition;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

class FileController
{
  public function __construct(private DatabaseInterface $database)
  {

  }

  public function infoJSON(ServerRequestInterface $request, ResponseInterface $response, string $project, string $platform, string $version, string $filename): ResponseInterface
  {
    // Verify the file exists in the database
    $file = $this->database->getFileByProjectSlugPlatformSlugVersionAndFilename($project, $platform, $version, $filename);
    if ($file == null) {
      // TODO: Return JSON?
      return $response
        ->withStatus(404);
    }

    // Generate the JSON body and return the response
    $response->getBody()->write(json_encode([
      'downloads' => $file['downloads']
    ]));
    return $response->withHeader('Content-Type', 'application/json');
  }

  public function info(ServerRequestInterface $request, ResponseInterface $response, Twig $twig, string $project, string $platform, string $version, string $filename): ResponseInterface
  {
    // Verify the file exists in the database
    $file = $this->database->getFileByProjectSlugPlatformSlugVersionAndFilename($project, $platform, $version, $filename);
    if ($file == null) {
      return $twig
        ->render($response, 'error.html.twig', [
          'status' => 404,
          'message' => 'Not Found'
        ])
        ->withStatus(404);
    }

    return $twig->render($response, 'file/stats.html.twig', [
      'path' => "/{$file['project_slug']}/{$file['platform_slug']}/{$file['release_version']}/",
      'file' => $file,
      'filename' => $file['filename'],
      'downloads' => $file['downloads']
    ]);
  }

  public function download(ServerRequestInterface $request, ResponseInterface $response, string $project, string $platform, string $version, string $filename): ResponseInterface
  {
    // Verify the file exists in the database
    $file = $this->database->getFileByProjectSlugPlatformSlugVersionAndFilename($project, $platform, $version, $filename);
    if ($file == null) {
      return $response->withStatus(404);
    }

    // Increment the download count
    $this->database->incrementFileDownloadCount($file['id']);

    // Record a download record
    // TODO: Record a detailed record

    // We determined the content type
    // TODO: Investigate if there are any security implications with setting mime type
    if (!empty($file['content_type'])) {
      $response = $response->withHeader('Content-Type', $file['content_type']);

      // If it's text, show it inline while also supplying the filename
      if (str_starts_with($file['content_type'], 'text/')) {
        $response = $response->withHeader('Content-Disposition', ContentDisposition::create($file['filename'], true, 'inline')->format());
      }
      // If it's not text, set the encoding to binary and force the file to download
      else {
        $response = $response
          ->withHeader('Content-Transfer-Encoding', 'Binary')
          ->withHeader('Content-Disposition', ContentDisposition::create($file['filename'], true, 'attachment')->format());
      }
    }
    // If we don't know the content type, just assume it's unknown binary format and force it to download
    else {
      $response = $response
        ->withHeader('Content-Type', 'application/octet-stream')
        ->withHeader('Content-Transfer-Encoding', 'Binary')
        ->withHeader('Content-Disposition', ContentDisposition::create($file['filename'], true, 'attachment')->format());
    }

    return $response
      // Disable cache
      ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
      ->withHeader('Pragma', 'no-cache')
      ->withHeader('Expires', '0')
      // Let nginx handle sending the file data itself
      ->withHeader('X-Accel-Redirect', "/files/{$file['project_slug']}/{$file['platform_slug']}/{$file['release_version']}/{$file['filename']}")
      ->withStatus(200);
  }
}
