<?php
/*=========================================================================
 Midas Server
 Copyright Kitware SAS, 26 rue Louis Guérin, 69100 Villeurbanne, France.
 All rights reserved.
 For more information visit http://www.kitware.com/.

 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at

         http://www.apache.org/licenses/LICENSE-2.0.txt

 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
=========================================================================*/

/**
 * Upgrade the tracker module to version 1.0.1. Add a user_id value to a scalar
 * record indicating which user uploaded the scalar, a binary "official" flag to
 * a scalar record indicating if it is an official or experimental submission,
 * and add a user_id index to the tracker_scalar table.
 */
class Tracker_Upgrade_1_0_1 extends MIDASUpgrade
{
    /** Upgrade a MySQL database. */
    public function mysql()
    {
        $this->db->query("ALTER TABLE `tracker_scalar` ADD COLUMN `user_id` bigint(20) NOT NULL DEFAULT '-1';");
        $this->db->query("ALTER TABLE `tracker_scalar` ADD COLUMN `official` tinyint(4) NOT NULL DEFAULT '1';");
        $this->db->query('ALTER TABLE `tracker_scalar` ADD KEY (`user_id`);');
    }

    /** Upgrade a PostgreSQL database. */
    public function pgsql()
    {
        $this->db->query('ALTER TABLE tracker_scalar ADD COLUMN user_id bigint NOT NULL DEFAULT -1::bigint;');
        $this->db->query('ALTER TABLE tracker_scalar ADD COLUMN official smallint NOT NULL DEFAULT 1::smallint;');
        $this->db->query('CREATE INDEX tracker_scalar_idx_user_id ON tracker_scalar (user_id);');
    }
}
