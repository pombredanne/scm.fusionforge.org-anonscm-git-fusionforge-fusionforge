select setval('bug_pk_seq', (select max(bug_id)+1 from bug));
select setval('bug_bug_dependencies_pk_seq', (select max(bug_depend_id)+1 from bug_bug_dependencies));
select setval('bug_canned_responses_pk_seq', (select max(bug_canned_id)+1 from bug_canned_responses));
select setval('bug_category_pk_seq', (select max(bug_category_id)+1 from bug_category));
select setval('bug_filter_pk_seq', (select max(filter_id)+1 from bug_filter));
select setval('bug_group_pk_seq', (select max(bug_group_id)+1 from bug_group));
select setval('bug_history_pk_seq', (select max(bug_history_id)+1 from bug_history));
select setval('bug_resolution_pk_seq', (select max(resolution_id)+1 from bug_resolution));
select setval('bug_status_pk_seq', (select max(status_id)+1 from bug_status));
select setval('bug_task_dependencies_pk_seq', (select max(bug_depend_id)+1 from bug_task_dependencies));
select setval('canned_responses_pk_seq', (select max(response_id)+1 from canned_responses));
select setval('db_images_pk_seq', (select max(id)+1 from db_images));
select setval('doc_data_pk_seq', (select max(docid)+1 from doc_data));
select setval('doc_groups_pk_seq', (select max(doc_group)+1 from doc_groups));
select setval('doc_states_pk_seq', (select max(stateid)+1 from doc_states));
select setval('filemodule_monitor_pk_seq', (select max(id)+1 from filemodule_monitor));
select setval('forum_pk_seq', (select max(msg_id)+1 from forum));
select setval('forum_group_list_pk_seq', (select max(group_forum_id)+1 from forum_group_list));
select setval('forum_monitored_forums_pk_seq', (select max(monitor_id)+1 from forum_monitored_forums));
select setval('forum_saved_place_pk_seq', (select max(saved_place_id)+1 from forum_saved_place));
select setval('foundry_news_pk_seq', (select max(foundry_news_id)+1 from foundry_news));
select setval('foundry_preferred_projec_pk_seq', (select max(foundry_project_id)+1 from foundry_preferred_projects));
select setval('foundry_projects_pk_seq', (select max(id)+1 from foundry_projects));
select setval('frs_file_pk_seq', (select max(file_id)+1 from frs_file));
select setval('frs_filetype_pk_seq', (select max(type_id)+1 from frs_filetype));
select setval('frs_package_pk_seq', (select max(package_id)+1 from frs_package));
select setval('frs_processor_pk_seq', (select max(processor_id)+1 from frs_processor));
select setval('frs_release_pk_seq', (select max(release_id)+1 from frs_release));
select setval('frs_status_pk_seq', (select max(status_id)+1 from frs_status));
select setval('group_cvs_history_pk_seq', (select max(id)+1 from group_cvs_history));
select setval('group_history_pk_seq', (select max(group_history_id)+1 from group_history));
select setval('group_type_pk_seq', (select max(type_id)+1 from group_type));
select setval('groups_pk_seq', (select max(group_id)+1 from groups));
select setval('mail_group_list_pk_seq', (select max(group_list_id)+1 from mail_group_list));
select setval('news_bytes_pk_seq', (select max(id)+1 from news_bytes));
select setval('patch_pk_seq', (select max(patch_id)+1 from patch));
select setval('patch_category_pk_seq', (select max(patch_category_id)+1 from patch_category));
select setval('patch_history_pk_seq', (select max(patch_history_id)+1 from patch_history));
select setval('patch_status_pk_seq', (select max(patch_status_id)+1 from patch_status));
select setval('people_job_pk_seq', (select max(job_id)+1 from people_job));
select setval('people_job_category_pk_seq', (select max(category_id)+1 from people_job_category));
select setval('people_job_inventory_pk_seq', (select max(job_inventory_id)+1 from people_job_inventory));
select setval('people_job_status_pk_seq', (select max(status_id)+1 from people_job_status));
select setval('people_skill_pk_seq', (select max(skill_id)+1 from people_skill));
select setval('people_skill_inventory_pk_seq', (select max(skill_inventory_id)+1 from people_skill_inventory));
select setval('people_skill_level_pk_seq', (select max(skill_level_id)+1 from people_skill_level));
select setval('people_skill_year_pk_seq', (select max(skill_year_id)+1 from people_skill_year));
select setval('project_assigned_to_pk_seq', (select max(project_assigned_id)+1 from project_assigned_to));
select setval('project_dependencies_pk_seq', (select max(project_depend_id)+1 from project_dependencies));
select setval('project_group_list_pk_seq', (select max(group_project_id)+1 from project_group_list));
select setval('project_history_pk_seq', (select max(project_history_id)+1 from project_history));
select setval('project_metric_pk_seq', (select max(ranking)+1 from project_metric));
select setval('project_metric_tmp1_pk_seq', (select max(ranking)+1 from project_metric_tmp1));
select setval('project_status_pk_seq', (select max(status_id)+1 from project_status));
select setval('project_task_pk_seq', (select max(project_task_id)+1 from project_task));
select setval('project_weekly_metric_pk_seq', (select max(ranking)+1 from project_weekly_metric));
select setval('snippet_pk_seq', (select max(snippet_id)+1 from snippet));
select setval('snippet_package_pk_seq', (select max(snippet_package_id)+1 from snippet_package));
select setval('snippet_package_item_pk_seq', (select max(snippet_package_item_id)+1 from snippet_package_item));
select setval('snippet_package_version_pk_seq', (select max(snippet_package_id)+1 from snippet_package_version));
select setval('snippet_version_pk_seq', (select max(snippet_version_id)+1 from snippet_version));
select setval('support_pk_seq', (select max(support_id)+1 from support));
select setval('support_canned_responses_pk_seq', (select max(support_canned_id)+1 from support_canned_responses));
select setval('support_category_pk_seq', (select max(support_category_id)+1 from support_category));
select setval('support_history_pk_seq', (select max(support_history_id)+1 from support_history));
select setval('support_messages_pk_seq', (select max(support_message_id)+1 from support_messages));
select setval('support_status_pk_seq', (select max(support_status_id)+1 from support_status));
select setval('supported_languages_pk_seq', (select max(language_id)+1 from supported_languages));
select setval('survey_question_types_pk_seq', (select max(id)+1 from survey_question_types));
select setval('survey_questions_pk_seq', (select max(question_id)+1 from survey_questions));
select setval('surveys_pk_seq', (select max(survey_id)+1 from surveys));
select setval('themes_pk_seq', (select max(theme_id)+1 from themes));
select setval('trove_cat_pk_seq', (select max(trove_cat_id)+1 from trove_cat));
select setval('trove_group_link_pk_seq', (select max(trove_group_id)+1 from trove_group_link));
select setval('trove_treesums_pk_seq', (select max(trove_treesums_id)+1 from trove_treesums));
select setval('user_bookmarks_pk_seq', (select max(bookmark_id)+1 from user_bookmarks));
select setval('user_diary_pk_seq', (select max(id)+1 from user_diary));
select setval('user_diary_monitor_pk_seq', (select max(monitor_id)+1 from user_diary_monitor));
select setval('user_group_pk_seq', (select max(user_group_id)+1 from user_group));
select setval('user_metric_pk_seq', (select max(ranking)+1 from user_metric));
select setval('user_metric0_pk_seq', (select max(ranking)+1 from user_metric0));
select setval('users_pk_seq', (select max(user_id)+1 from users));
