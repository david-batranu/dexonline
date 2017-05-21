<<<<<<< HEAD
CREATE TABLE Visual3D (
  id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  path VARCHAR(255) NOT NULL,
  userId INT(11) NOT NULL,
  createDate INT(11),
  modDate INT(11)
) DEFAULT CHARACTER SET "utf8" ENGINE=InnoDB;

CREATE TABLE VisualTag3D (
  id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  modelId INT(11),
  meshName VARCHAR(255) NOT NULL,
  camera VARCHAR(255) NOT NULL,
  entryId INT(11),
  createDate INT(11),
  modDate INT(11)
) DEFAULT CHARACTER SET "utf8" ENGINE=InnoDB;

=======
alter table Entry add key(structStatus);
alter table Source add key(structurable);
>>>>>>> master
