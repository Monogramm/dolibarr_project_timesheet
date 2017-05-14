-- ===================================================================
-- Copyright (C) 2013  Alexandre Spangaro <alexandre.spangaro@gmail.com>
-- Copyright (C) 2015  Patrick Delcroix <pmpdelcroix@gmail.com>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program. If not, see <http://www.gnu.org/licenses/>.
--
-- ===================================================================
-- TS Revision 1.5.0

-- this table is used to store the timesheet favorit


CREATE TABLE llx_project_task_time_approval
(
rowid                   integer     NOT NULL AUTO_INCREMENT,
date_start              DATE        NOT NULL, -- start date of the period
date_end             DATE        NOT NULL, -- start date of the period
status                  enum('DRAFT','SUBMITTED','APPROVED','CANCELLED','REJECTED','CHALLENGED','INVOICED','UNDERAPPROVAL','PLANNED') DEFAULT 'DRAFT',
sender                  enum('team','project','customer','provider','other','user') DEFAULT 'user', -- a team ts is always needed 
recipient               enum('team','project','customer','provider','other','user') DEFAULT 'user', -- a team ts is always needed 
note                  VARCHAR(1024), -- in case target is not team, querry on task
planned_workload                integer DEFAULT NULL,
tracking              CHAR(9) DEFAULT 'x00000000' NOT NULL, -- show which role approved the TS
fk_user_tracking       VARCHAR(128) DEFAULT NULL, -- list of the IDs who approved the TS
date_creation         DATETIME      NOT NULL,
date_modification     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,  
fk_userid             integer  NOT NULL,          -- timesheet user (redondant)
fk_user_creation      integer,
fk_user_modification  integer  default NULL,
fk_projet_task               integer NOT NULL, -- task linked
fk_project_task_timesheet     integer NOT NULL,
PRIMARY KEY (rowid)
) 
ENGINE=innodb;


