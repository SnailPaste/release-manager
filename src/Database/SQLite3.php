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

namespace App\Database;

use PDO;
use PDOException;

class SQLite3 implements DatabaseInterface
{
  /**
   * @var PDO SQLite3 PDO database object
   */
  private PDO $db;

  private const DATABASE_VERSION = 1;

  public function __construct(array $db_config)
  {
    // Open the database
    try {
      $this->db = new PDO("sqlite:{$db_config['path']}", '', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
      ]);
    } catch(PDOException $e) {
      // TODO: Proper error handling/logging
      die($e->getMessage());
    }

    // Database upgrade
    try {
      // Check if the database needs to be upgraded
      $stmt = $this->db->query("PRAGMA user_version");
      $version = (int)$stmt->fetchColumn();
      if ($version < self::DATABASE_VERSION) {
        // Disable foreign key integrity checks during the upgrade
        //$this->db->query('PRAGMA foreign_keys=off');

        // Begin a transaction for the upgrade process
        $this->db->beginTransaction();

        // If this is a clean install, create the initial tables
        if ($version < 1) {
          $this->db->query("CREATE TABLE assets (id INTEGER PRIMARY KEY, filepath TEXT UNIQUE, downloads INTEGER DEFAULT 0)");
        }

        // Update the user_version now that we've updated the schema
        $this->db->query("PRAGMA user_version = ".self::DATABASE_VERSION);

        // Commit the transaction
        $this->db->commit();
      }
    } catch(PDOException $e) {
      // Rollback the attempted upgrade
      $this->db->rollBack();
      // TODO: Proper error handling/logging
      die($e->getMessage());
    }

    try {
      // Enable foreign key integrity checks
      $this->db->query('PRAGMA foreign_keys=on');
    } catch (PDOException $e) {
      // TODO: Proper error handling/logging
      die($e->getMessage());
    }
  }

  public function getDownloadByPath(string $path): ?array
  {
    try {
      $stmt = $this->db->prepare("SELECT * FROM assets WHERE filepath = :filepath");
      $stmt->execute(['filepath' => $path]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($row !== false) {
        return $row;
      }
      return null;
    } catch (PDOException $e) {
      // TODO: Proper error handling/logging
      die($e->getMessage());
    }
  }

  public function incrementDownloadCountByPath(string $path): bool
  {
    try {
      $stmt = $this->db->prepare("UPDATE assets SET downloads = downloads + 1 WHERE filepath = :filepath");
      $stmt->execute(['filepath' => $path]);
      return $stmt->rowCount() == 1;
    } catch (PDOException $e) {
      // TODO: Proper error handling/logging
      die($e->getMessage());
    }
  }

  public function addDownload(array $data): bool
  {
    try {
      $stmt = $this->db->prepare("INSERT INTO assets (filepath) VALUES (:filepath)");
      $stmt->execute($data);
      return $stmt->rowCount() == 1;
    } catch (PDOException $e) {
      // TODO: Proper error handling/logging
      die($e->getMessage());
    }
  }
}
