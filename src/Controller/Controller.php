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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

class Controller
{
  public function errorPage(ServerRequestInterface $request, ResponseInterface $response, int $status): ResponseInterface
  {
    $message = "Unknown server error";
    switch($status) {
      case 404: $message = "Page Not Found";
        break;
      case 500: $message = "Server Error";
        break;
    }
    $view = Twig::fromRequest($request);
    return $view
      ->render($response, 'error.html.twig', compact(['status', 'message']))
      ->withStatus($status);
  }
}
