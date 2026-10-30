-- Hello Hobnobbers profile/verification v2 migration for an existing database.
-- BACK UP YOUR DATABASE BEFORE RUNNING THIS FILE.
ALTER TABLE user_profiles DROP COLUMN profile_photo_path;
ALTER TABLE user_profiles MODIFY marital_status ENUM('never_married','separated','divorced','widowed') NOT NULL;
ALTER TABLE user_verifications DROP COLUMN doc_number;
ALTER TABLE user_verifications MODIFY doc_type ENUM('aadhaar','pan','voter_id','dl','any_govt') NOT NULL;
