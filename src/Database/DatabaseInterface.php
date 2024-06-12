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

namespace App\Database;

interface DatabaseInterface
{
  public function __construct(array $db_config);

  public function getProject(int $id): ?array;

  public function getProjectBySlug(string $slug): ?array;

  public function getProjects(): array;

  public function addProject(string $name, string $slug, string $vcs_url = null): ?int;


  public function getRelease(int $id): ?array;

  public function getReleaseByProjectSlugAndVersion(string $project_slug, string $version): ?array;

  public function getReleasesByProjectSlug(string $project_slug): array;

  public function addRelease(int $project, string $version, string $semver, string $release_date, string $vcs_tag = null, string $title = null, string $summary = null, string $changelog = null, string $discussion_url = null): ?int;

  public function getPlatform(int $id): ?array;

  public function getPlatformBySlug(string $slug): ?array;

  public function addPlatform(string $name, string $slug): ?int;

  public function getFile(int $id): ?array;

  public function getFileByProjectSlugPlatformSlugVersionAndFilename(string $project_slug, string $platform_slug, string $version, string $filename): ?array;

  public function getFilesByProject(int $project_id): ?array;

  public function incrementFileDownloadCount(int $id): bool;

  public function addFile(int $project, int $release, int $platform, string $filename, string $content_type, string $sha256): ?int;
}
