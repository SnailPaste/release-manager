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

namespace App\Misc;

use League\CommonMark\GithubFlavoredMarkdownConverter;

class TwigCommonMark extends \Twig\Extension\AbstractExtension
{
  private GithubFlavoredMarkdownConverter $converter;

  public function __construct()
  {
    $this->converter = new GithubFlavoredMarkdownConverter([
      'allow_unsafe_links' => false
    ]);
  }

  public function getFilters(): array
  {
    return [
      new \Twig\TwigFilter('commonmark', [$this->converter, 'convert'], ['is_safe' => ['all']]),
    ];
  }
}
