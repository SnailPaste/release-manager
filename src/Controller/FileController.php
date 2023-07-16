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

namespace App\Controller;

use App\Database\DatabaseInterface;
use cardinalby\ContentDisposition\ContentDisposition;
use League\Config\Configuration;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

class FileController extends Controller
{
  private DatabaseInterface $database;
  private Configuration $config;

  public function __construct(DatabaseInterface $database, Configuration $config)
  {
    $this->database = $database;
    $this->config = $config;
  }

  public function statsJSON(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
  {
    // Verify the file exists in the database
    $file = $this->database->getDownloadByPath($args['filepath']);
    if ($file == null) {
      return $this->errorPage($request, $response, 404);
    }

    // Generate the JSON body and return the response
    $response->getBody()->write(json_encode([
      'path' => dirname($file['filepath']).'/',
      'filename' => basename($file['filepath']),
      'downloads' => $file['downloads']
    ]));
    return $response->withHeader('Content-Type', 'application/json');
  }

  public function stats(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
  {
    // Verify the file exists in the database
    $file = $this->database->getDownloadByPath($args['filepath']);
    if ($file == null) {
      return $this->errorPage($request, $response, 404);
    }

    $view = Twig::fromRequest($request);
    return $view->render($response, 'files/file_stats.html.twig', [
      'path' => dirname($file['filepath']).'/',
      'filename' => basename($file['filepath']),
      'downloads' => $file['downloads']
    ]);
  }

  public function download(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
  {
    // Verify the file exists in the database
    $file = $this->database->getDownloadByPath($args['filepath']);
    if ($file == null) {
      return $this->errorPage($request, $response, 404);
    }

    // Increment the download count
    $this->database->incrementDownloadCountByPath($file['filepath']);

    // Record a download record
    // TODO: Record a detailed record

    // Extract the filename
    $filename = basename($file['filepath']);

    // Check the file type
    $content_type = @mime_content_type($this->config->get('files_root').$file['filepath']);

    // We determined the content type
    // TODO: Investigate if there are any security implications with setting mime type
    if ($content_type !== false) {
      $response = $response->withHeader('Content-Type', $content_type);

      // If it's text, show it inline while also supplying the filename
      if (str_starts_with($content_type, 'text/')) {
        $response = $response->withHeader('Content-Disposition', ContentDisposition::create($filename, true, 'inline')->format());
      }
      // If it's not text, set the encoding to binary and force the file to download
      else {
        $response = $response
          ->withHeader('Content-Transfer-Encoding', 'Binary')
          ->withHeader('Content-Disposition', ContentDisposition::create($filename, true, 'attachment')->format());
      }
    }
    // If we don't know the content type, just assume it's unknown binary format and force it to download
    else {
      $response = $response
        ->withHeader('Content-Type', 'application/octet-stream')
        ->withHeader('Content-Transfer-Encoding', 'Binary')
        ->withHeader('Content-Disposition', ContentDisposition::create($filename, true, 'attachment')->format());
    }

    return $response
      ->withHeader('X-Accel-Redirect', "/files{$file['filepath']}")
      ->withStatus(200);
  }
}
