ALTER TABLE project_task DROP CONSTRAINT "project_task_group_project_id_f" RESTRICT;
DROP TABLE project_category;
DROP SEQUENCE project_categor_category_id_seq;
DROP VIEW project_task_vw;
DROP TABLE project_task_artifact;
DROP TABLE project_group_forum;
DROP TABLE project_group_doccat;
DROP VIEW project_depend_vw;
DROP VIEW project_dependon_vw;
DROP VIEW project_history_user_vw;
DROP VIEW project_message_user_vw;
DROP TRIGGER projtask_update_depend_trig ON project_task;
DROP TRIGGER projtask_insert_depend_trig ON project_task;
