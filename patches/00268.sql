CREATE TABLE IF NOT EXISTS UserSelection (
  id int NOT NULL AUTO_INCREMENT,
  userId int NOT NULL,
  name varchar(255) NOT NULL,
  createDate int NOT NULL DEFAULT 0,
  modDate int NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS SelectionSource (
  id int NOT NULL AUTO_INCREMENT,
  selectionId int NOT NULL,
  sourceId int NOT NULL,
  createDate int NOT NULL DEFAULT 0,
  modDate int NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=MyISAM;
