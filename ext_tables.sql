CREATE TABLE tx_csloauth2_oauth_clients (
    uid int(11) NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,

    tstamp int(11) DEFAULT '0' NOT NULL,
    crdate int(11) DEFAULT '0' NOT NULL,
    cruser_id int(11) DEFAULT '0' NOT NULL,
    deleted tinyint(4) DEFAULT '0' NOT NULL,
    hidden tinyint(4) DEFAULT '0' NOT NULL,

    name varchar(80) DEFAULT '' NOT NULL,
    typo3_context char(2) DEFAULT 'BE' NOT NULL,

    client_id varchar(80) DEFAULT '' NOT NULL,
    client_secret varchar(80) DEFAULT NULL,
    redirect_uri varchar(2000) DEFAULT '' NOT NULL,
    grant_types varchar(80) DEFAULT NULL,
    scope varchar(100) DEFAULT NULL,
    user_id varchar(80) DEFAULT NULL,

    PRIMARY KEY (uid),
    KEY parent (pid),
    UNIQUE KEY clients_client_id (client_id)
);

CREATE TABLE tx_csloauth2_oauth_access_tokens (
    access_token varchar(40) DEFAULT '' NOT NULL,
    client_id varchar(80) DEFAULT '' NOT NULL,
    user_id varchar(255) DEFAULT NULL,
    expires timestamp DEFAULT 'CURRENT_TIMESTAMP' on update CURRENT_TIMESTAMP NOT NULL,
    scope varchar(2000) DEFAULT NULL,

    PRIMARY KEY (access_token)
);

CREATE TABLE tx_csloauth2_oauth_authorization_codes (
    authorization_code varchar(40) DEFAULT '' NOT NULL,
    client_id varchar(80) DEFAULT '' NOT NULL,
    user_id varchar(255) DEFAULT NULL,
    redirect_uri varchar(2000) DEFAULT NULL,
    expires timestamp DEFAULT 'CURRENT_TIMESTAMP' on update CURRENT_TIMESTAMP NOT NULL,
    scope varchar(2000) DEFAULT NULL,

    PRIMARY KEY (authorization_code)
);

CREATE TABLE tx_csloauth2_oauth_refresh_tokens (
    refresh_token varchar(40) DEFAULT '' NOT NULL,
    client_id varchar(80) DEFAULT '' NOT NULL,
    user_id varchar(255) DEFAULT NULL,
    expires timestamp DEFAULT 'CURRENT_TIMESTAMP' on update CURRENT_TIMESTAMP NOT NULL,
    scope varchar(2000) DEFAULT NULL,

    PRIMARY KEY (refresh_token)
);

CREATE TABLE tx_csloauth2_oauth_users (
    username varchar(255) DEFAULT '' NOT NULL,
    password varchar(2000) DEFAULT NULL,
    first_name varchar(255) DEFAULT NULL,
    last_name varchar(255) DEFAULT NULL,

    PRIMARY KEY (username)
);

CREATE TABLE tx_csloauth2_oauth_scopes (
    scope text,
    is_default tinyint(1) DEFAULT '0' NOT NULL
);

CREATE TABLE tx_csloauth2_oauth_jwt (
    client_id varchar(80) DEFAULT '' NOT NULL,
    subject varchar(80) DEFAULT NULL,
    public_key varchar(2000) DEFAULT NULL,

    PRIMARY KEY (client_id)
);
