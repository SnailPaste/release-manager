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

use PDO;
use PDOException;

class SQLite3 implements DatabaseInterface
{
  /**
   * @var PDO SQLite3 PDO database object
   */
  private PDO $pdo;

  private const DATABASE_VERSION = 1;

  public function __construct(array $db_config)
  {
    // Open the database
    try {
      $this->pdo = new PDO("sqlite:{$db_config['path']}", '', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
      ]);
    } catch(PDOException $e) {
      // TODO: Proper error handling/logging
      die($e->getMessage());
    }

    // Database upgrade
    try {
      // Check if the database needs to be upgraded
      $stmt = $this->pdo->query("PRAGMA user_version");
      $version = (int)$stmt->fetchColumn();
      if ($version < self::DATABASE_VERSION) {
        // Disable foreign key integrity checks during the upgrade
        //$this->pdo->query('PRAGMA foreign_keys=off');

        // Begin a transaction for the upgrade process
        $this->pdo->beginTransaction();

        // If this is a clean install, create the initial tables
        if ($version < 1) {
          // TODO: Add architecture table and a file_architecture table to map files to architectures?
          $this->pdo->query('CREATE TABLE "project" ("id" INTEGER PRIMARY KEY, "name" TEXT, "slug" TEXT, "vcs_url" TEXT, "description" TEXT)');
          $this->pdo->query('CREATE UNIQUE INDEX idx_project_slug_unq ON "project"("slug")');

          $this->pdo->query('CREATE TABLE "release" ("id" INTEGER PRIMARY KEY, "project" INTEGER, "version" TEXT, "semver" TEXT, "release_date" DATETIME, "vcs_tag" TEXT, "title" TEXT, "summary" TEXT, "changelog" TEXT, "discussion_url" TEXT, "public" BOOLEAN DEFAULT TRUE)');
          $this->pdo->query('CREATE INDEX idx_release_project ON "release"("project")');
          $this->pdo->query('CREATE UNIQUE INDEX idx_release_project_version_unq ON "release"("project", "version")');

          $this->pdo->query('CREATE TABLE "platform" ("id" INTEGER PRIMARY KEY, "name" TEXT, "slug" TEXT UNIQUE)');
          $this->pdo->query('CREATE UNIQUE INDEX idx_platform_slug_unq ON "platform"("slug")');

          // TODO: Remove the 'project' column since the release table already has that?
          $this->pdo->query('CREATE TABLE "file" ("id" INTEGER PRIMARY KEY, "project" INTEGER, "release" INTEGER, "platform" INTEGER, "filename" TEXT, "content_type" TEXT, "sha256" TEXT, "downloads" INTEGER DEFAULT 0)');
          $this->pdo->query('CREATE UNIQUE INDEX idx_file_project_release_platform_filename_unq ON "file"("project", "release", "platform", "filename")');
        }

        // Update the user_version now that we've updated the schema
        $this->pdo->query("PRAGMA user_version = ".self::DATABASE_VERSION);

        // Commit the transaction
        $this->pdo->commit();
      }
    } catch(PDOException $e) {
      // Rollback the attempted upgrade
      $this->pdo->rollBack();
      // TODO: Proper error handling/logging
      die($e->getMessage());
    }

    /*try {
      // Enable foreign key integrity checks
      $this->pdo->query('PRAGMA foreign_keys=on');
    } catch (PDOException $e) {
      // TODO: Proper error handling/logging
      die($e->getMessage());
    }*/
  }

  public function getProject(int $id): ?array
  {
    try {
      $stmt = $this->pdo->prepare('SELECT * FROM "project" WHERE "id" = :id');
      $stmt->bindParam(':id', $id, PDO::PARAM_INT);
      $stmt->execute();
      $row = $stmt->fetch();
      if ($row !== false) {
        return $row;
      }
    } catch (PDOException $e) {
      // TODO: Log failures
    }

    return null;
  }

  public function getProjectBySlug(string $slug): ?array
  {
    try {
      $stmt = $this->pdo->prepare('SELECT * FROM "project" WHERE "slug" = :slug');
      $stmt->bindParam(':slug', $slug);
      $stmt->execute();
      $row = $stmt->fetch();
      if ($row !== false) {
        return $row;
      }
    } catch (PDOException $e) {
      // TODO: Log failures
    }

    return null;
  }

  public function getProjects(): array
  {
    try {
      $sql = <<<SQLite
        SELECT p."name", p."slug", p."description",
               COUNT(DISTINCT r."id") as release_count,
               SUM(f."downloads") as total_downloads
        FROM "project" p
        LEFT JOIN "release" r ON r."project" = p."id"
        LEFT JOIN "file" f ON f."release" = r."id"
        GROUP BY p."id"
      SQLite;

      $stmt = $this->pdo->prepare($sql);
      $stmt->execute();
      return $stmt->fetchAll();
    } catch (PDOException $e) {
      // TODO: Log failures
    }

    return [];
  }

  public function addProject(string $name, string $slug, string $vcs_url = null, string $description = null): ?int
  {
    try {
      $stmt = $this->pdo->prepare('INSERT INTO "project" ("name", "slug", "vcs_url", "description") VALUES (:name, :slug, :vcs_url, :description)');
      $stmt->bindParam(':name', $name);
      $stmt->bindParam(':slug', $slug);
      $stmt->bindValue('vcs_url', $vcs_url);
      $stmt->bindValue('description', $description);
      $stmt->execute();
      $id = $this->pdo->lastInsertId();
      if ($id !== false) {
        return (int)$id;
      }
    } catch (PDOException $e) {
      // TODO: Log failures
    }

    return null;
  }

  public function getRelease(int $id): ?array
  {
    try {
      $stmt = $this->pdo->prepare('SELECT * FROM "release" WHERE "id" = :id');
      $stmt->bindParam(':id', $id, PDO::PARAM_INT);
      $stmt->execute();
      $row = $stmt->fetch();
      if ($row !== false) {
        return $row;
      }
    } catch (PDOException $e) {
      // TODO: Log failures
    }

    return null;
  }

  public function getReleaseByProjectSlugAndVersion(string $project_slug, string $version): ?array
  {
    try {
      $stmt = $this->pdo->prepare('SELECT r."id", r."project", p."slug" as project_slug, r."version", r."vcs_tag", r."title", r."summary", r."changelog", r."discussion_url" FROM "release" r INNER JOIN "project" p ON p."id" = r."project" WHERE p."slug" = :project_slug AND r."version" = :version');
      $stmt->bindParam(':project_slug', $project_slug);
      $stmt->bindParam(':version', $version);
      $stmt->execute();
      $row = $stmt->fetch();
      if ($row !== false) {
        return $row;
      }
    } catch (PDOException $e) {
      // TODO: Log failures
    }

    return null;
  }

  public function getReleasesByProjectSlug(string $project_slug): array
  {
    try {
      $stmt = $this->pdo->prepare('SELECT r."id", r."project", p."slug" as project_slug, r."version", r."semver", r."release_date", r."vcs_tag", r."title", r."summary", r."changelog", r."discussion_url" FROM "release" r INNER JOIN "project" p ON p."id" = r."project" WHERE p."slug" = :project_slug AND "public" = TRUE ORDER BY release_date DESC');
      $stmt->bindParam(':project_slug', $project_slug);
      $stmt->execute();
      return $stmt->fetchAll();
    } catch (PDOException $e) {
      // TODO: Log failures
    }

    return [];
  }

  public function addRelease(int $project, string $version, string $semver, string $release_date, string $vcs_tag = null, string $title = null, string $summary = null, string $changelog = null, string $discussion_url = null, bool $public = true): ?int
  {
    try {
      $stmt = $this->pdo->prepare('INSERT INTO "release" ("project", "version", "semver", "release_date", "vcs_tag", "title", "summary", "changelog", "discussion_url", "public") VALUES (:project, :version, :semver, :release_date, :vcs_tag, :title, :summary, :changelog, :discussion_url, :public)');
      $stmt->bindParam(':project', $project, PDO::PARAM_INT);
      $stmt->bindParam(':version', $version);
      $stmt->bindParam(':semver', $semver);
      $stmt->bindParam(':release_date', $release_date);
      $stmt->bindParam(':vcs_tag', $vcs_tag);
      $stmt->bindParam(':title', $title);
      $stmt->bindParam(':summary', $summary);
      $stmt->bindParam(':changelog', $changelog);
      $stmt->bindParam(':discussion_url', $discussion_url);
      $stmt->bindParam(':public', $public, PDO::PARAM_BOOL);
      $stmt->execute();
      $id = $this->pdo->lastInsertId();
      if ($id !== false) {
        return (int)$id;
      }
    } catch(PDOException $e) {
      // TODO: Log failure
    }

    return null;
  }

  public function getPlatform(int $id): ?array
  {
    try {
      $stmt = $this->pdo->prepare('SELECT * FROM "platform" WHERE "id" = :id');
      $stmt->bindParam(':id', $id, PDO::PARAM_INT);
      $stmt->execute();
      $row = $stmt->fetch();
      if ($row !== false) {
        return $row;
      }
    } catch (PDOException $e) {
      // TODO: Log failures
    }

    return null;
  }

  public function getPlatformBySlug(string $slug): ?array
  {
    try {
      $stmt = $this->pdo->prepare('SELECT * FROM "platform" WHERE "slug" = :slug');
      $stmt->bindParam(':slug', $slug);
      $stmt->execute();
      $row = $stmt->fetch();
      if ($row !== false) {
        return $row;
      }
    } catch (PDOException $e) {
      // TODO: Log failures
    }

    return null;
  }

  public function getPlatforms(): ?array
  {
    try {
      $stmt = $this->pdo->prepare('SELECT * FROM "platform"');
      $stmt->execute();
      return $stmt->fetchAll();
    } catch (PDOException $e) {
      // TODO: Log failures
    }

    return null;
  }

  public function addPlatform(string $name, string $slug): ?int
  {
    try {
      $stmt = $this->pdo->prepare('INSERT INTO "platform" ("name", "slug") VALUES (:name, :slug)');
      $stmt->bindParam(':name', $name);
      $stmt->bindParam(':slug', $slug);
      $stmt->execute();
      $id = $this->pdo->lastInsertId();
      if ($id !== false) {
        return (int)$id;
      }
    } catch (PDOException $e) {
      // TODO: Log failures
    }

    return null;
  }

  public function getFile(int $id): ?array
  {
    try {
      $stmt = $this->pdo->prepare('SELECT * FROM "file" WHERE "id" = :id');
      $stmt->bindParam(':id', $id, PDO::PARAM_INT);
      $stmt->execute();
      $row = $stmt->fetch();
      if ($row !== false) {
        return $row;
      }
    } catch (PDOException $e) {
      // TODO: Log failures
    }

    return null;
  }

  public function getFileByProjectSlugPlatformSlugVersionAndFilename(string $project_slug, string $platform_slug, string $version, string $filename): ?array
  {
    try {
      $sql = <<<SQLite
        SELECT f."id", f."filename", f."content_type", f."downloads",
               f."project", p."slug" as project_slug, p."name" as project_name,
               f."platform", l."slug" as platform_slug, l."name" as platform_name,
               f."release", r."version" as release_version, r."title" as release_title, r."summary" as release_sumary,
               r."changelog" as release_changelog, r."discussion_url" as release_discussion_url
        FROM "file" f
        INNER JOIN "project" p ON f."project" = p."id"
        INNER JOIN "platform" l ON f."platform" = l."id"
        INNER JOIN "release" r ON f."release" = r."id"
        WHERE p."slug" = :project_slug AND l."slug" = :platform_slug AND r."version" = :version AND f."filename" = :filename
      SQLite;

      $stmt = $this->pdo->prepare($sql);
      $stmt->bindParam(':project_slug', $project_slug);
      $stmt->bindParam(':platform_slug', $platform_slug);
      $stmt->bindParam(':version', $version);
      $stmt->bindParam(':filename', $filename);
      $stmt->execute();
      $row = $stmt->fetch();
      if ($row !== false) {
        return $row;
      }
    } catch (PDOException $e) {
      // TODO: Log failures
    }

    return null;
  }

  public function getFilesByProject(int $project_id): ?array
  {
    try {
      $sql = <<<SQLite
        SELECT f."id", f."release", l."name" as platform_name, l."slug" as platform_slug, f."filename", f."content_type", f."sha256", f."downloads"
        FROM "file" f
        INNER JOIN "platform" l ON f."platform" = l."id"
        WHERE "project" = :project_id
      SQLite;

      $stmt = $this->pdo->prepare($sql);
      $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
      $stmt->execute();
      return $stmt->fetchAll();
    } catch(PDOException $e) {
      // TODO: Log failures
    }

    return null;
  }

  public function incrementFileDownloadCount(int $id): bool
  {
    try {
      $stmt = $this->pdo->prepare('UPDATE "file" SET "downloads" = "downloads" + 1 WHERE "id" = :id');
      $stmt->bindParam(':id', $id, PDO::PARAM_INT);
      $stmt->execute();
      return $stmt->rowCount() === 1;
    } catch (PDOException $e) {
      // TODO: Log failures
    }

    return false;
  }

  public function addFile(int $project, int $release, int $platform, string $filename, string $content_type, string $sha256): ?int
  {
    try {
      $stmt = $this->pdo->prepare('INSERT INTO "file" ("project", "release", "platform", "filename", "content_type", "sha256") VALUES (:project, :release, :platform, :filename, :content_type, :sha256)');
      $stmt->bindParam(':project', $project, PDO::PARAM_INT);
      $stmt->bindParam(':release', $release, PDO::PARAM_INT);
      $stmt->bindParam(':platform', $platform, PDO::PARAM_INT);
      $stmt->bindParam(':filename', $filename);
      $stmt->bindParam(':content_type', $content_type);
      $stmt->bindParam(':sha256', $sha256);
      $stmt->execute();
      $id = $this->pdo->lastInsertId();
      if ($id !== false) {
        return (int)$id;
      }
    } catch(PDOException $e) {
      // TODO: Log failure
    }

    return null;
  }
}
