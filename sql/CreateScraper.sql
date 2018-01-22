CREATE TABLE WebScraper (
    URL VARCHAR(255),
    WordPress BOOLEAN,
    Theme_Name VARCHAR(255),
	  WordPress_Version VARCHAR(255),
    WhoIs_Email VARCHAR(255),
    Status VARCHAR(255),
    PRIMARY KEY (URL)
);

INSERT INTO `WebScraper` (`URL`, `WordPress`, `Theme_Name`, `WordPress_Version`, `WhoIs_Email`)
VALUES ('https://hgking.com', '1', 'Theme', 'WordPress.com', 'hgking@gmail');

CREATE TABLE CSV (
    Finished BOOLEAN,
    Total INT,
    Filename VARCHAR(255),
    PRIMARY KEY (Filename)
);

INSERT INTO `CSV` (`Finished`, `Total`, `Filename`)
VALUES ('1', '0', 'file.csv');
