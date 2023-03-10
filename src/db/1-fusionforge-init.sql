--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


--
-- Name: unaccent; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS unaccent WITH SCHEMA public;


--
-- Name: EXTENSION unaccent; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION unaccent IS 'text search dictionary that removes accents';


SET search_path = public, pg_catalog;

--
-- Name: artifactgroup_update_agg(); Type: FUNCTION; Schema: public; Owner: fusionforge
--

CREATE FUNCTION artifactgroup_update_agg() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
	--
	-- see if they are moving to a new artifacttype
	-- if so, its a more complex operation
	--
	IF NEW.group_artifact_id <> OLD.group_artifact_id THEN
		--
		-- transferred artifacts always have a status of 1
		-- so we will increment the new artifacttypes sums
		--
		IF OLD.status_id=3 THEN
			-- No need to decrement counters on old tracker
		ELSE
			IF OLD.status_id=2 THEN
				UPDATE artifact_counts_agg SET count=count-1
					WHERE group_artifact_id=OLD.group_artifact_id;
			ELSE
				IF OLD.status_id=1 THEN
					UPDATE artifact_counts_agg SET count=count-1,open_count=open_count-1
						WHERE group_artifact_id=OLD.group_artifact_id;
				END IF;
			END IF;
		END IF;

		IF NEW.status_id=3 THEN
			--DO NOTHING
		ELSE
			IF NEW.status_id=2 THEN
					UPDATE artifact_counts_agg SET count=count+1
						WHERE group_artifact_id=NEW.group_artifact_id;
			ELSE
				IF NEW.status_id=1 THEN
					UPDATE artifact_counts_agg SET count=count+1, open_count=open_count+1
						WHERE group_artifact_id=NEW.group_artifact_id;
				END IF;
			END IF;
		END IF;
	ELSE
		--
		-- just need to evaluate the status flag and
		-- increment/decrement the counter as necessary
		--
		IF NEW.status_id <> OLD.status_id THEN
			IF NEW.status_id = 1 THEN
				IF OLD.status_id=2 THEN
					UPDATE artifact_counts_agg SET open_count=open_count+1
						WHERE group_artifact_id=NEW.group_artifact_id;
				ELSE
					IF OLD.status_id=3 THEN
						UPDATE artifact_counts_agg SET open_count=open_count+1, count=count+1
							WHERE group_artifact_id=NEW.group_artifact_id;
					END IF;
				END IF;
			ELSE
				IF NEW.status_id = 2 THEN
					IF OLD.status_id=1 THEN
						UPDATE artifact_counts_agg SET open_count=open_count-1
							WHERE group_artifact_id=NEW.group_artifact_id;
					ELSE
						IF OLD.status_id=3 THEN
							UPDATE artifact_counts_agg SET count=count+1
								WHERE group_artifact_id=NEW.group_artifact_id;
						END IF;
					END IF;
				ELSE
					IF NEW.status_id = 3 THEN
						IF OLD.status_id=2 THEN
							UPDATE artifact_counts_agg SET count=count-1
								WHERE group_artifact_id=NEW.group_artifact_id;
						ELSE
							IF OLD.status_id=1 THEN
								UPDATE artifact_counts_agg SET open_count=open_count-1,count=count-1
									WHERE group_artifact_id=NEW.group_artifact_id;
							END IF;
						END IF;
					END IF;
				END IF;
			END IF;
		END IF;
	END IF;
	RETURN NEW;
END;
$$;


ALTER FUNCTION public.artifactgroup_update_agg() OWNER TO fusionforge;

--
-- Name: artifactgrouplist_insert_agg(); Type: FUNCTION; Schema: public; Owner: fusionforge
--

CREATE FUNCTION artifactgrouplist_insert_agg() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    INSERT INTO artifact_counts_agg (group_artifact_id,count,open_count)
        VALUES (NEW.group_artifact_id,0,0);
        RETURN NEW;
END;
$$;


ALTER FUNCTION public.artifactgrouplist_insert_agg() OWNER TO fusionforge;

--
-- Name: forumgrouplist_insert_agg(); Type: FUNCTION; Schema: public; Owner: fusionforge
--

CREATE FUNCTION forumgrouplist_insert_agg() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
        INSERT INTO forum_agg_msg_count (group_forum_id,count)
                VALUES (NEW.group_forum_id,0);
        RETURN NEW;
END;
$$;


ALTER FUNCTION public.forumgrouplist_insert_agg() OWNER TO fusionforge;

--
-- Name: frs_dlstats_filetotal_insert_ag(); Type: FUNCTION; Schema: public; Owner: fusionforge
--

CREATE FUNCTION frs_dlstats_filetotal_insert_ag() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
	INSERT INTO frs_dlstats_filetotal_agg (file_id, downloads) VALUES (NEW.file_id, 0);
	RETURN NEW;
END;
$$;


ALTER FUNCTION public.frs_dlstats_filetotal_insert_ag() OWNER TO fusionforge;

--
-- Name: project_sums(); Type: FUNCTION; Schema: public; Owner: fusionforge
--

CREATE FUNCTION project_sums() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
	DECLARE
		num integer;
		curr_group integer;
		found integer;
	BEGIN
		---
		--- Get number of things this group has now
		---
		IF TG_ARGV[0]='surv' THEN
			IF TG_OP='DELETE' THEN
				SELECT INTO num count(*) FROM surveys WHERE OLD.group_id=group_id AND is_active=1;
				curr_group := OLD.group_id;
			ELSE
				SELECT INTO num count(*) FROM surveys WHERE NEW.group_id=group_id AND is_active=1;
				curr_group := NEW.group_id;
			END IF;
		END IF;
		IF TG_ARGV[0]='mail' THEN
			IF TG_OP='DELETE' THEN
				SELECT INTO num count(*) FROM mail_group_list WHERE OLD.group_id=group_id AND is_public=1;
				curr_group := OLD.group_id;
			ELSE
				SELECT INTO num count(*) FROM mail_group_list WHERE NEW.group_id=group_id AND is_public=1;
				curr_group := NEW.group_id;
			END IF;
		END IF;
		IF TG_ARGV[0]='fmsg' THEN
			IF TG_OP='DELETE' THEN
				SELECT INTO curr_group group_id FROM forum_group_list WHERE OLD.group_forum_id=group_forum_id;
				SELECT INTO num count(*) FROM forum, forum_group_list WHERE forum.group_forum_id=forum_group_list.group_forum_id AND forum_group_list.is_public=1 AND forum_group_list.group_id=curr_group;
			ELSE
				SELECT INTO curr_group group_id FROM forum_group_list WHERE NEW.group_forum_id=group_forum_id;
				SELECT INTO num count(*) FROM forum, forum_group_list WHERE forum.group_forum_id=forum_group_list.group_forum_id AND forum_group_list.is_public=1 AND forum_group_list.group_id=curr_group;
			END IF;
		END IF;
		IF TG_ARGV[0]='fora' THEN
			IF TG_OP='DELETE' THEN
				SELECT INTO num count(*) FROM forum_group_list WHERE OLD.group_id=group_id AND is_public=1;
				curr_group = OLD.group_id;
				--- also need to update message count
				DELETE FROM project_sums_agg WHERE group_id=OLD.group_id AND type='fmsg';
				INSERT INTO project_sums_agg
					SELECT OLD.group_id,'fmsg'::text AS type, count(forum.msg_id) AS count
					FROM forum, forum_group_list
					WHERE forum.group_forum_id=forum_group_list.group_forum_id AND forum_group_list.is_public=1 AND forum_group_list.group_id=OLD.group_id GROUP BY group_id,type;
			ELSE
				SELECT INTO num count(*) FROM forum_group_list WHERE NEW.group_id=group_id AND is_public=1;
				curr_group = NEW.group_id;
			END IF;
		END IF;
		---
		--- See if this group already has a row in project_sums_agg for these things
		---
		SELECT INTO found count(group_id) FROM project_sums_agg WHERE curr_group=group_id AND type=TG_ARGV[0];

		IF found=0 THEN
			---
			--- Create row for this group
			---
			INSERT INTO project_sums_agg
				VALUES (curr_group, TG_ARGV[0], num);
		ELSE
			---
			--- Update count
			---
			UPDATE project_sums_agg SET count=num
			WHERE curr_group=group_id AND type=TG_ARGV[0];
		END IF;

		IF TG_OP='DELETE' THEN
			RETURN OLD;
		ELSE
			RETURN NEW;
		END IF;
	END;
$$;


ALTER FUNCTION public.project_sums() OWNER TO fusionforge;

--
-- Name: projectgroup_update_agg(); Type: FUNCTION; Schema: public; Owner: fusionforge
--

CREATE FUNCTION projectgroup_update_agg() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    --
    -- see if they are moving to a new subproject
    -- if so, its a more complex operation
    --
    IF NEW.group_project_id <> OLD.group_project_id THEN
        --
        -- transferred tasks always have a status of 1
        -- so we will increment the new subprojects sums
        --
        IF OLD.status_id=3 THEN
            -- No need to decrement counters on old tracker
        ELSE
            IF OLD.status_id=2 THEN
                UPDATE project_counts_agg SET count=count-1
                    WHERE group_project_id=OLD.group_project_id;
            ELSE
                IF OLD.status_id=1 THEN
                    UPDATE project_counts_agg SET count=count-1,open_count=open_count-1
                        WHERE group_project_id=OLD.group_project_id;
                END IF;
            END IF;
        END IF;

        IF NEW.status_id=3 THEN
            --DO NOTHING
        ELSE
            IF NEW.status_id=2 THEN
                    UPDATE project_counts_agg SET count=count+1
                        WHERE group_project_id=NEW.group_project_id;
            ELSE
                IF NEW.status_id=1 THEN
                    UPDATE project_counts_agg SET count=count+1, open_count=open_count+1
                        WHERE group_project_id=NEW.group_project_id;
                END IF;
            END IF;
        END IF;
    ELSE
        --
        -- just need to evaluate the status flag and
        -- increment/decrement the counter as necessary
        --
        IF NEW.status_id <> OLD.status_id THEN
            IF NEW.status_id = 1 THEN
                IF OLD.status_id=2 THEN
                    UPDATE project_counts_agg SET open_count=open_count+1
                        WHERE group_project_id=NEW.group_project_id;
                ELSE
                    IF OLD.status_id=3 THEN
                        UPDATE project_counts_agg SET open_count=open_count+1, count=count+1
                            WHERE group_project_id=NEW.group_project_id;
                    END IF;
                END IF;
            ELSE
                IF NEW.status_id = 2 THEN
                    IF OLD.status_id=1 THEN
                        UPDATE project_counts_agg SET open_count=open_count-1
                            WHERE group_project_id=NEW.group_project_id;
                    ELSE
                        IF OLD.status_id=3 THEN
                            UPDATE project_counts_agg SET count=count+1
                                WHERE group_project_id=NEW.group_project_id;
                        END IF;
                    END IF;
                ELSE
                    IF NEW.status_id = 3 THEN
                        IF OLD.status_id=2 THEN
                            UPDATE project_counts_agg SET count=count-1
                                WHERE group_project_id=NEW.group_project_id;
                        ELSE
                            IF OLD.status_id=1 THEN
                                UPDATE project_counts_agg SET open_count=open_count-1,count=count-1
                                    WHERE group_project_id=NEW.group_project_id;
                            END IF;
                        END IF;
                    END IF;
                END IF;
            END IF;
        END IF;
    END IF;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.projectgroup_update_agg() OWNER TO fusionforge;

--
-- Name: projectgrouplist_insert_agg(); Type: FUNCTION; Schema: public; Owner: fusionforge
--

CREATE FUNCTION projectgrouplist_insert_agg() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    INSERT INTO project_counts_agg (group_project_id,count,open_count)
        VALUES (NEW.group_project_id,0,0);
        RETURN NEW;
END;
$$;


ALTER FUNCTION public.projectgrouplist_insert_agg() OWNER TO fusionforge;

--
-- Name: update_last_modified_date(); Type: FUNCTION; Schema: public; Owner: fusionforge
--

CREATE FUNCTION update_last_modified_date() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
NEW.last_modified_date = EXTRACT(EPOCH FROM now())::integer;
RETURN NEW;
END;
$$;


ALTER FUNCTION public.update_last_modified_date() OWNER TO fusionforge;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: activity_log; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE activity_log (
    day integer DEFAULT 0 NOT NULL,
    hour integer DEFAULT 0 NOT NULL,
    group_id integer DEFAULT 0 NOT NULL,
    browser character varying(8) DEFAULT 'OTHER'::character varying NOT NULL,
    ver double precision DEFAULT (0)::double precision NOT NULL,
    platform character varying(8) DEFAULT 'OTHER'::character varying NOT NULL,
    "time" integer DEFAULT 0 NOT NULL,
    page text,
    type integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.activity_log OWNER TO fusionforge;

--
-- Name: artifact; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE artifact (
    artifact_id integer DEFAULT nextval(('"artifact_artifact_id_seq"'::text)::regclass) NOT NULL,
    group_artifact_id integer NOT NULL,
    status_id integer DEFAULT 1 NOT NULL,
    priority integer DEFAULT 3 NOT NULL,
    submitted_by integer DEFAULT 100 NOT NULL,
    assigned_to integer DEFAULT 100 NOT NULL,
    open_date integer DEFAULT 0 NOT NULL,
    close_date integer DEFAULT 0 NOT NULL,
    summary text NOT NULL,
    details text NOT NULL,
    last_modified_date integer
);


ALTER TABLE public.artifact OWNER TO fusionforge;

--
-- Name: artifact_group_list; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE artifact_group_list (
    group_artifact_id integer DEFAULT nextval(('"artifact_grou_group_artifac_seq"'::text)::regclass) NOT NULL,
    group_id integer NOT NULL,
    name text,
    description text,
    is_public integer DEFAULT 0 NOT NULL,
    allow_anon integer DEFAULT 0 NOT NULL,
    email_all_updates integer DEFAULT 0 NOT NULL,
    email_address text NOT NULL,
    due_period integer DEFAULT 2592000 NOT NULL,
    submit_instructions text,
    browse_instructions text,
    datatype integer DEFAULT 0 NOT NULL,
    status_timeout integer,
    custom_status_field integer DEFAULT 0 NOT NULL,
    custom_renderer text,
    browse_list text DEFAULT 'summary,open_date,assigned_to,submitted_by'::text NOT NULL
);


ALTER TABLE public.artifact_group_list OWNER TO fusionforge;

--
-- Name: forum; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE forum (
    msg_id integer DEFAULT nextval(('forum_pk_seq'::text)::regclass) NOT NULL,
    group_forum_id integer DEFAULT 0 NOT NULL,
    posted_by integer DEFAULT 0 NOT NULL,
    subject text DEFAULT ''::text NOT NULL,
    body text DEFAULT ''::text NOT NULL,
    post_date integer DEFAULT 0 NOT NULL,
    is_followup_to integer DEFAULT 0 NOT NULL,
    thread_id integer DEFAULT 0 NOT NULL,
    has_followups integer DEFAULT 0,
    most_recent_date integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.forum OWNER TO fusionforge;

--
-- Name: forum_group_list; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE forum_group_list (
    group_forum_id integer DEFAULT nextval(('forum_group_list_pk_seq'::text)::regclass) NOT NULL,
    group_id integer DEFAULT 0 NOT NULL,
    forum_name text DEFAULT ''::text NOT NULL,
    is_public integer DEFAULT 0 NOT NULL,
    description text,
    allow_anonymous integer DEFAULT 0 NOT NULL,
    send_all_posts_to text,
    moderation_level integer DEFAULT 0
);


ALTER TABLE public.forum_group_list OWNER TO fusionforge;

--
-- Name: frs_package; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE frs_package (
    package_id integer DEFAULT nextval(('frs_package_pk_seq'::text)::regclass) NOT NULL,
    group_id integer DEFAULT 0 NOT NULL,
    name text,
    status_id integer DEFAULT 0 NOT NULL,
    is_public integer DEFAULT 1
);


ALTER TABLE public.frs_package OWNER TO fusionforge;

--
-- Name: frs_release; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE frs_release (
    release_id integer DEFAULT nextval(('frs_release_pk_seq'::text)::regclass) NOT NULL,
    package_id integer DEFAULT 0 NOT NULL,
    name text,
    notes text,
    changes text,
    status_id integer DEFAULT 0 NOT NULL,
    preformatted integer DEFAULT 0 NOT NULL,
    release_date integer DEFAULT 0 NOT NULL,
    released_by integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.frs_release OWNER TO fusionforge;

--
-- Name: plugin_cvstracker_data_artifact; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE plugin_cvstracker_data_artifact (
    id integer DEFAULT nextval(('plugin_cvstracker_artifact_seq'::text)::regclass) NOT NULL,
    kind integer DEFAULT 0 NOT NULL,
    group_artifact_id integer,
    project_task_id integer
);


ALTER TABLE public.plugin_cvstracker_data_artifact OWNER TO fusionforge;

--
-- Name: plugin_cvstracker_data_master; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE plugin_cvstracker_data_master (
    id integer DEFAULT nextval(('plugin_cvstracker_master_seq'::text)::regclass) NOT NULL,
    holder_id integer NOT NULL,
    log_text text DEFAULT ''::text,
    file text DEFAULT ''::text NOT NULL,
    prev_version text DEFAULT ''::text,
    actual_version text DEFAULT ''::text,
    author text DEFAULT ''::text NOT NULL,
    cvs_date integer NOT NULL
);


ALTER TABLE public.plugin_cvstracker_data_master OWNER TO fusionforge;

--
-- Name: users; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE users (
    user_id integer DEFAULT nextval(('users_pk_seq'::text)::regclass) NOT NULL,
    user_name text DEFAULT ''::text NOT NULL,
    email text DEFAULT ''::text NOT NULL,
    user_pw character varying(32) DEFAULT ''::character varying NOT NULL,
    realname character varying(32) DEFAULT ''::character varying NOT NULL,
    status character(1) DEFAULT 'A'::bpchar NOT NULL,
    shell character varying(20) DEFAULT '/bin/bash'::character varying NOT NULL,
    unix_pw character varying(40) DEFAULT ''::character varying NOT NULL,
    unix_status character(1) DEFAULT 'N'::bpchar NOT NULL,
    unix_uid integer DEFAULT 0 NOT NULL,
    unix_box character varying(10) DEFAULT 'shell1'::character varying NOT NULL,
    add_date integer DEFAULT 0 NOT NULL,
    confirm_hash character varying(32),
    mail_siteupdates integer DEFAULT 0 NOT NULL,
    mail_va integer DEFAULT 0 NOT NULL,
    authorized_keys text,
    email_new text,
    people_view_skills integer DEFAULT 0 NOT NULL,
    people_resume text DEFAULT ''::text NOT NULL,
    timezone character varying(64) DEFAULT 'GMT'::character varying,
    language integer DEFAULT 1 NOT NULL,
    block_ratings integer DEFAULT 0,
    jabber_address text,
    jabber_only integer,
    address text,
    phone text,
    fax text,
    title text,
    firstname character varying(60),
    lastname character varying(60),
    address2 text,
    ccode character(2) DEFAULT 'US'::bpchar,
    theme_id integer,
    type_id integer DEFAULT 1,
    unix_gid integer DEFAULT 0
);


ALTER TABLE public.users OWNER TO fusionforge;

--
-- Name: activity_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW activity_vw AS
    (((SELECT agl.group_id, 'trackeropen'::text AS section, agl.group_artifact_id AS ref_id, a.artifact_id AS subref_id, a.summary AS description, a.open_date AS activity_date, u.user_id, u.user_name, u.realname FROM (artifact_group_list agl JOIN artifact a USING (group_artifact_id)), users u WHERE (u.user_id = a.submitted_by) UNION SELECT agl.group_id, 'trackerclose'::text AS section, agl.group_artifact_id AS ref_id, a.artifact_id AS subref_id, a.summary AS description, a.close_date AS activity_date, u.user_id, u.user_name, u.realname FROM (artifact_group_list agl JOIN artifact a USING (group_artifact_id)), users u WHERE ((u.user_id = a.assigned_to) AND (a.close_date > 0))) UNION SELECT agl.group_id, 'commit'::text AS section, agl.group_artifact_id AS ref_id, a.artifact_id AS subref_id, pcdm.log_text AS description, pcdm.cvs_date AS activity_date, u.user_id, u.user_name, u.realname FROM (artifact_group_list agl JOIN artifact a USING (group_artifact_id)), plugin_cvstracker_data_master pcdm, plugin_cvstracker_data_artifact pcda, users u WHERE (((pcdm.holder_id = pcda.id) AND (pcda.group_artifact_id = a.artifact_id)) AND (u.user_name = pcdm.author))) UNION SELECT frsp.group_id, 'frsrelease'::text AS section, frsp.package_id AS ref_id, frsr.release_id AS subref_id, frsr.name AS description, frsr.release_date AS activity_date, u.user_id, u.user_name, u.realname FROM (frs_package frsp JOIN frs_release frsr USING (package_id)), users u WHERE (u.user_id = frsr.released_by)) UNION SELECT fgl.group_id, 'forumpost'::text AS section, fgl.group_forum_id AS ref_id, forum.msg_id AS subref_id, forum.subject AS description, forum.post_date AS activity_date, u.user_id, u.user_name, u.realname FROM (forum_group_list fgl JOIN forum USING (group_forum_id)), users u WHERE (u.user_id = forum.posted_by);


ALTER TABLE public.activity_vw OWNER TO fusionforge;

--
-- Name: artifact_artifact_id_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE artifact_artifact_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.artifact_artifact_id_seq OWNER TO fusionforge;

--
-- Name: artifact_canned_response_id_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE artifact_canned_response_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.artifact_canned_response_id_seq OWNER TO fusionforge;

--
-- Name: artifact_canned_responses; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE artifact_canned_responses (
    id integer DEFAULT nextval(('"artifact_canned_response_id_seq"'::text)::regclass) NOT NULL,
    group_artifact_id integer NOT NULL,
    title text NOT NULL,
    body text NOT NULL
);


ALTER TABLE public.artifact_canned_responses OWNER TO fusionforge;

--
-- Name: artifact_counts_agg; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE artifact_counts_agg (
    group_artifact_id integer NOT NULL,
    count integer DEFAULT 0 NOT NULL,
    open_count integer DEFAULT 0
);


ALTER TABLE public.artifact_counts_agg OWNER TO fusionforge;

--
-- Name: artifact_extra_field_data; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE artifact_extra_field_data (
    data_id integer DEFAULT nextval(('artifact_extra_field_data_data_id_seq'::text)::regclass) NOT NULL,
    artifact_id integer NOT NULL,
    field_data text,
    extra_field_id integer DEFAULT 0
);


ALTER TABLE public.artifact_extra_field_data OWNER TO fusionforge;

--
-- Name: artifact_extra_field_data_data_id_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE artifact_extra_field_data_data_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.artifact_extra_field_data_data_id_seq OWNER TO fusionforge;

--
-- Name: artifact_extra_field_elements; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE artifact_extra_field_elements (
    element_id integer DEFAULT nextval(('artifact_extra_field_elements_element_id_seq'::text)::regclass) NOT NULL,
    extra_field_id integer NOT NULL,
    element_name text NOT NULL,
    status_id integer DEFAULT 0 NOT NULL,
    element_pos integer
);


ALTER TABLE public.artifact_extra_field_elements OWNER TO fusionforge;

--
-- Name: artifact_extra_field_elements_element_id_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE artifact_extra_field_elements_element_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.artifact_extra_field_elements_element_id_seq OWNER TO fusionforge;

--
-- Name: artifact_extra_field_list; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE artifact_extra_field_list (
    extra_field_id integer DEFAULT nextval(('artifact_extra_field_list_extra_field_id_seq'::text)::regclass) NOT NULL,
    group_artifact_id integer NOT NULL,
    field_name text NOT NULL,
    field_type integer DEFAULT 1,
    attribute1 integer DEFAULT 0,
    attribute2 integer DEFAULT 0,
    is_required integer DEFAULT 0 NOT NULL,
    alias text
);


ALTER TABLE public.artifact_extra_field_list OWNER TO fusionforge;

--
-- Name: artifact_extra_field_list_extra_field_id_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE artifact_extra_field_list_extra_field_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.artifact_extra_field_list_extra_field_id_seq OWNER TO fusionforge;

--
-- Name: artifact_file; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE artifact_file (
    id integer DEFAULT nextval(('"artifact_file_id_seq"'::text)::regclass) NOT NULL,
    artifact_id integer NOT NULL,
    description text NOT NULL,
    bin_data text NOT NULL,
    filename text NOT NULL,
    filesize integer NOT NULL,
    filetype text NOT NULL,
    adddate integer DEFAULT 0 NOT NULL,
    submitted_by integer NOT NULL
);


ALTER TABLE public.artifact_file OWNER TO fusionforge;

--
-- Name: artifact_file_id_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE artifact_file_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.artifact_file_id_seq OWNER TO fusionforge;

--
-- Name: artifact_file_user_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW artifact_file_user_vw AS
    SELECT af.id, af.artifact_id, af.description, af.bin_data, af.filename, af.filesize, af.filetype, af.adddate, af.submitted_by, users.user_name, users.realname FROM artifact_file af, users WHERE (af.submitted_by = users.user_id);


ALTER TABLE public.artifact_file_user_vw OWNER TO fusionforge;

--
-- Name: artifact_grou_group_artifac_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE artifact_grou_group_artifac_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.artifact_grou_group_artifac_seq OWNER TO fusionforge;

--
-- Name: artifact_group_list_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW artifact_group_list_vw AS
    SELECT agl.group_artifact_id, agl.group_id, agl.name, agl.description, agl.is_public, agl.allow_anon, agl.email_all_updates, agl.email_address, agl.due_period, agl.submit_instructions, agl.browse_instructions, agl.browse_list, agl.datatype, agl.status_timeout, agl.custom_status_field, agl.custom_renderer, aca.count, aca.open_count FROM (artifact_group_list agl LEFT JOIN artifact_counts_agg aca USING (group_artifact_id));


ALTER TABLE public.artifact_group_list_vw OWNER TO fusionforge;

--
-- Name: artifact_history; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE artifact_history (
    id integer DEFAULT nextval(('"artifact_history_id_seq"'::text)::regclass) NOT NULL,
    artifact_id integer DEFAULT 0 NOT NULL,
    field_name text DEFAULT ''::text NOT NULL,
    old_value text DEFAULT ''::text NOT NULL,
    mod_by integer DEFAULT 0 NOT NULL,
    entrydate integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.artifact_history OWNER TO fusionforge;

--
-- Name: artifact_history_id_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE artifact_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.artifact_history_id_seq OWNER TO fusionforge;

--
-- Name: artifact_history_user_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW artifact_history_user_vw AS
    SELECT ah.id, ah.artifact_id, ah.field_name, ah.old_value, ah.entrydate, users.user_name FROM artifact_history ah, users WHERE (ah.mod_by = users.user_id);


ALTER TABLE public.artifact_history_user_vw OWNER TO fusionforge;

--
-- Name: artifact_message; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE artifact_message (
    id integer DEFAULT nextval(('"artifact_message_id_seq"'::text)::regclass) NOT NULL,
    artifact_id integer NOT NULL,
    submitted_by integer NOT NULL,
    from_email text NOT NULL,
    adddate integer DEFAULT 0 NOT NULL,
    body text NOT NULL
);


ALTER TABLE public.artifact_message OWNER TO fusionforge;

--
-- Name: artifact_message_id_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE artifact_message_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.artifact_message_id_seq OWNER TO fusionforge;

--
-- Name: artifact_message_user_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW artifact_message_user_vw AS
    SELECT am.id, am.artifact_id, am.from_email, am.body, am.adddate, users.user_id, users.email, users.user_name, users.realname FROM artifact_message am, users WHERE (am.submitted_by = users.user_id);


ALTER TABLE public.artifact_message_user_vw OWNER TO fusionforge;

--
-- Name: artifact_monitor; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE artifact_monitor (
    id integer DEFAULT nextval(('"artifact_monitor_id_seq"'::text)::regclass) NOT NULL,
    artifact_id integer NOT NULL,
    user_id integer NOT NULL,
    email text
);


ALTER TABLE public.artifact_monitor OWNER TO fusionforge;

--
-- Name: artifact_monitor_id_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE artifact_monitor_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.artifact_monitor_id_seq OWNER TO fusionforge;

--
-- Name: role_setting; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE role_setting (
    role_id integer NOT NULL,
    section_name text NOT NULL,
    ref_id integer NOT NULL,
    value character varying(2) NOT NULL
);


ALTER TABLE public.role_setting OWNER TO fusionforge;

--
-- Name: user_group; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE user_group (
    user_group_id integer DEFAULT nextval(('user_group_pk_seq'::text)::regclass) NOT NULL,
    user_id integer DEFAULT 0 NOT NULL,
    group_id integer DEFAULT 0 NOT NULL,
    admin_flags character(16) DEFAULT ''::bpchar NOT NULL,
    forum_flags integer DEFAULT 0 NOT NULL,
    project_flags integer DEFAULT 2 NOT NULL,
    doc_flags integer DEFAULT 0 NOT NULL,
    cvs_flags integer DEFAULT 1 NOT NULL,
    member_role integer DEFAULT 100 NOT NULL,
    release_flags integer DEFAULT 0 NOT NULL,
    artifact_flags integer,
    role_id integer DEFAULT 1
);


ALTER TABLE public.user_group OWNER TO fusionforge;

--
-- Name: artifact_perm; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW artifact_perm AS
    SELECT 0 AS id, role_setting.ref_id AS group_artifact_id, user_group.user_id, (role_setting.value)::integer AS perm_level FROM role_setting, user_group WHERE ((user_group.role_id = role_setting.role_id) AND (role_setting.section_name = 'tracker'::text));


ALTER TABLE public.artifact_perm OWNER TO fusionforge;

--
-- Name: artifact_query; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE artifact_query (
    artifact_query_id integer NOT NULL,
    group_artifact_id integer NOT NULL,
    user_id integer NOT NULL,
    query_name text NOT NULL,
    query_type integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.artifact_query OWNER TO fusionforge;

--
-- Name: artifact_query_artifact_query_id_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE artifact_query_artifact_query_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.artifact_query_artifact_query_id_seq OWNER TO fusionforge;

--
-- Name: artifact_query_artifact_query_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: fusionforge
--

ALTER SEQUENCE artifact_query_artifact_query_id_seq OWNED BY artifact_query.artifact_query_id;


--
-- Name: artifact_query_fields; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE artifact_query_fields (
    artifact_query_id integer NOT NULL,
    query_field_type text NOT NULL,
    query_field_id integer NOT NULL,
    query_field_values text NOT NULL
);


ALTER TABLE public.artifact_query_fields OWNER TO fusionforge;

--
-- Name: artifact_status; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE artifact_status (
    id integer DEFAULT nextval(('"artifact_status_id_seq"'::text)::regclass) NOT NULL,
    status_name text NOT NULL
);


ALTER TABLE public.artifact_status OWNER TO fusionforge;

--
-- Name: artifact_status_id_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE artifact_status_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.artifact_status_id_seq OWNER TO fusionforge;

--
-- Name: artifact_type_monitor; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE artifact_type_monitor (
    group_artifact_id integer NOT NULL,
    user_id integer NOT NULL
);


ALTER TABLE public.artifact_type_monitor OWNER TO fusionforge;

--
-- Name: artifact_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW artifact_vw AS
    SELECT artifact.artifact_id, artifact.group_artifact_id, artifact.status_id, artifact.priority, artifact.submitted_by, artifact.assigned_to, artifact.open_date, artifact.close_date, artifact.summary, artifact.details, u.user_name AS assigned_unixname, u.realname AS assigned_realname, u.email AS assigned_email, u2.user_name AS submitted_unixname, u2.realname AS submitted_realname, u2.email AS submitted_email, artifact_status.status_name, artifact.last_modified_date FROM users u, users u2, artifact_status, artifact WHERE (((artifact.assigned_to = u.user_id) AND (artifact.submitted_by = u2.user_id)) AND (artifact.status_id = artifact_status.id));


ALTER TABLE public.artifact_vw OWNER TO fusionforge;

--
-- Name: artifact_workflow_event; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE artifact_workflow_event (
    event_id integer DEFAULT nextval(('"artifact_workflow_event_id_seq"'::text)::regclass) NOT NULL,
    group_artifact_id integer NOT NULL,
    field_id integer NOT NULL,
    from_value_id integer NOT NULL,
    to_value_id integer NOT NULL
);


ALTER TABLE public.artifact_workflow_event OWNER TO fusionforge;

--
-- Name: artifact_workflow_event_id_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE artifact_workflow_event_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.artifact_workflow_event_id_seq OWNER TO fusionforge;

--
-- Name: artifact_workflow_notify; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE artifact_workflow_notify (
    event_id integer NOT NULL,
    role_id integer NOT NULL
);


ALTER TABLE public.artifact_workflow_notify OWNER TO fusionforge;

--
-- Name: artifact_workflow_roles; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE artifact_workflow_roles (
    event_id integer NOT NULL,
    role_id integer NOT NULL
);


ALTER TABLE public.artifact_workflow_roles OWNER TO fusionforge;

--
-- Name: artifactperm_artgrouplist_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW artifactperm_artgrouplist_vw AS
    SELECT agl.group_artifact_id, agl.name, agl.description, agl.group_id, ap.user_id, ap.perm_level FROM artifact_perm ap, artifact_group_list agl WHERE (ap.group_artifact_id = agl.group_artifact_id);


ALTER TABLE public.artifactperm_artgrouplist_vw OWNER TO fusionforge;

--
-- Name: artifactperm_user_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW artifactperm_user_vw AS
    SELECT ap.id, ap.group_artifact_id, ap.user_id, ap.perm_level, users.user_name, users.realname FROM artifact_perm ap, users WHERE (users.user_id = ap.user_id);


ALTER TABLE public.artifactperm_user_vw OWNER TO fusionforge;

--
-- Name: canned_responses; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE canned_responses (
    response_id integer DEFAULT nextval(('canned_responses_pk_seq'::text)::regclass) NOT NULL,
    response_title character varying(25),
    response_text text
);


ALTER TABLE public.canned_responses OWNER TO fusionforge;

--
-- Name: canned_responses_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE canned_responses_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.canned_responses_pk_seq OWNER TO fusionforge;

--
-- Name: country_code; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE country_code (
    country_name character varying(80),
    ccode character(2) NOT NULL
);


ALTER TABLE public.country_code OWNER TO fusionforge;

--
-- Name: cron_history; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE cron_history (
    rundate integer NOT NULL,
    job text,
    output text
);


ALTER TABLE public.cron_history OWNER TO fusionforge;

--
-- Name: database_changes; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE database_changes (
    filename text
);


ALTER TABLE public.database_changes OWNER TO fusionforge;

--
-- Name: database_startpoint; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE database_startpoint (
    db_version character varying(10),
    db_start_date integer
);


ALTER TABLE public.database_startpoint OWNER TO fusionforge;

--
-- Name: db_images; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE db_images (
    id integer DEFAULT nextval(('db_images_pk_seq'::text)::regclass) NOT NULL,
    group_id integer DEFAULT 0 NOT NULL,
    description text DEFAULT ''::text NOT NULL,
    bin_data text DEFAULT ''::text NOT NULL,
    filename text DEFAULT ''::text NOT NULL,
    filesize integer DEFAULT 0 NOT NULL,
    filetype text DEFAULT ''::text NOT NULL,
    width integer DEFAULT 0 NOT NULL,
    height integer DEFAULT 0 NOT NULL,
    upload_date integer,
    version integer
);


ALTER TABLE public.db_images OWNER TO fusionforge;

--
-- Name: db_images_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE db_images_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.db_images_pk_seq OWNER TO fusionforge;

--
-- Name: deleted_groups; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE deleted_groups (
    unix_group_name character varying(30),
    delete_date integer,
    isdeleted integer
);


ALTER TABLE public.deleted_groups OWNER TO fusionforge;

--
-- Name: deleted_mailing_lists; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE deleted_mailing_lists (
    mailing_list_name character varying(30),
    delete_date integer,
    isdeleted integer
);


ALTER TABLE public.deleted_mailing_lists OWNER TO fusionforge;

--
-- Name: doc_data; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE doc_data (
    docid integer DEFAULT nextval(('doc_data_pk_seq'::text)::regclass) NOT NULL,
    stateid integer DEFAULT 0 NOT NULL,
    title character varying(255) DEFAULT ''::character varying NOT NULL,
    data text DEFAULT ''::text NOT NULL,
    updatedate integer DEFAULT 0 NOT NULL,
    createdate integer DEFAULT 0 NOT NULL,
    created_by integer DEFAULT 0 NOT NULL,
    doc_group integer DEFAULT 0 NOT NULL,
    description text,
    language_id integer DEFAULT 1 NOT NULL,
    filename text,
    filetype text,
    group_id integer,
    filesize integer DEFAULT 0 NOT NULL,
    data_words text DEFAULT ''::text NOT NULL
);


ALTER TABLE public.doc_data OWNER TO fusionforge;

--
-- Name: doc_data_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE doc_data_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.doc_data_pk_seq OWNER TO fusionforge;

--
-- Name: doc_groups; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE doc_groups (
    doc_group integer DEFAULT nextval(('doc_groups_pk_seq'::text)::regclass) NOT NULL,
    groupname character varying(255) DEFAULT ''::character varying NOT NULL,
    group_id integer DEFAULT 0 NOT NULL,
    parent_doc_group integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.doc_groups OWNER TO fusionforge;

--
-- Name: doc_groups_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE doc_groups_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.doc_groups_pk_seq OWNER TO fusionforge;

--
-- Name: doc_states; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE doc_states (
    stateid integer DEFAULT nextval(('doc_states_pk_seq'::text)::regclass) NOT NULL,
    name character varying(255) DEFAULT ''::character varying NOT NULL
);


ALTER TABLE public.doc_states OWNER TO fusionforge;

--
-- Name: doc_states_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE doc_states_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.doc_states_pk_seq OWNER TO fusionforge;

--
-- Name: supported_languages; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE supported_languages (
    language_id integer DEFAULT nextval(('"supported_langu_language_id_seq"'::text)::regclass) NOT NULL,
    name text,
    filename text,
    classname text,
    language_code character(5)
);


ALTER TABLE public.supported_languages OWNER TO fusionforge;

--
-- Name: docdata_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW docdata_vw AS
    SELECT users.user_name, users.realname, users.email, d.group_id, d.docid, d.stateid, d.title, d.updatedate, d.createdate, d.created_by, d.doc_group, d.description, d.language_id, d.filename, d.filetype, d.filesize, doc_states.name AS state_name, doc_groups.groupname AS group_name, sl.name AS language_name FROM ((((doc_data d NATURAL JOIN doc_states) NATURAL JOIN doc_groups) JOIN supported_languages sl ON ((sl.language_id = d.language_id))) JOIN users ON ((users.user_id = d.created_by)));


ALTER TABLE public.docdata_vw OWNER TO fusionforge;

--
-- Name: filemodule_monitor; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE filemodule_monitor (
    id integer DEFAULT nextval(('filemodule_monitor_pk_seq'::text)::regclass) NOT NULL,
    filemodule_id integer DEFAULT 0 NOT NULL,
    user_id integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.filemodule_monitor OWNER TO fusionforge;

--
-- Name: filemodule_monitor_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE filemodule_monitor_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.filemodule_monitor_pk_seq OWNER TO fusionforge;

--
-- Name: form_keys; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE form_keys (
    key_id integer NOT NULL,
    key text NOT NULL,
    creation_date integer NOT NULL,
    is_used integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.form_keys OWNER TO fusionforge;

--
-- Name: form_keys_key_id_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE form_keys_key_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.form_keys_key_id_seq OWNER TO fusionforge;

--
-- Name: form_keys_key_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: fusionforge
--

ALTER SEQUENCE form_keys_key_id_seq OWNED BY form_keys.key_id;


--
-- Name: forum_agg_msg_count; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE forum_agg_msg_count (
    group_forum_id integer DEFAULT 0 NOT NULL,
    count integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.forum_agg_msg_count OWNER TO fusionforge;

--
-- Name: forum_attachment; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE forum_attachment (
    attachmentid integer NOT NULL,
    userid integer DEFAULT 100 NOT NULL,
    dateline integer DEFAULT 0 NOT NULL,
    filename character varying(100) DEFAULT ''::character varying NOT NULL,
    filedata text NOT NULL,
    visible smallint DEFAULT (0)::smallint NOT NULL,
    counter smallint DEFAULT (0)::smallint NOT NULL,
    filesize integer DEFAULT 0 NOT NULL,
    msg_id integer DEFAULT 0 NOT NULL,
    filehash character varying(32) DEFAULT ''::character varying NOT NULL,
    mimetype character varying(32) DEFAULT 'application/octet-stream'::character varying NOT NULL
);


ALTER TABLE public.forum_attachment OWNER TO fusionforge;

--
-- Name: forum_attachment_attachmentid_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE forum_attachment_attachmentid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.forum_attachment_attachmentid_seq OWNER TO fusionforge;

--
-- Name: forum_attachment_attachmentid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: fusionforge
--

ALTER SEQUENCE forum_attachment_attachmentid_seq OWNED BY forum_attachment.attachmentid;


--
-- Name: forum_attachment_type; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE forum_attachment_type (
    extension character varying(20) DEFAULT ''::character varying NOT NULL,
    mimetype character varying(255) DEFAULT ''::character varying NOT NULL,
    size integer DEFAULT 0 NOT NULL,
    width smallint DEFAULT (0)::smallint NOT NULL,
    height smallint DEFAULT (0)::smallint NOT NULL,
    enabled smallint DEFAULT (1)::smallint NOT NULL
);


ALTER TABLE public.forum_attachment_type OWNER TO fusionforge;

--
-- Name: forum_group_list_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE forum_group_list_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.forum_group_list_pk_seq OWNER TO fusionforge;

--
-- Name: forum_group_list_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW forum_group_list_vw AS
    SELECT forum_group_list.group_forum_id, forum_group_list.group_id, forum_group_list.forum_name, forum_group_list.is_public, forum_group_list.description, forum_group_list.allow_anonymous, forum_group_list.send_all_posts_to, forum_group_list.moderation_level, forum_agg_msg_count.count AS total, (SELECT max(forum.post_date) AS recent FROM forum WHERE (forum.group_forum_id = forum_group_list.group_forum_id)) AS recent, (SELECT count(*) AS count FROM (SELECT forum.thread_id FROM forum WHERE (forum.group_forum_id = forum_group_list.group_forum_id) GROUP BY forum.thread_id) tmp) AS threads FROM (forum_group_list LEFT JOIN forum_agg_msg_count USING (group_forum_id));


ALTER TABLE public.forum_group_list_vw OWNER TO fusionforge;

--
-- Name: forum_monitored_forums; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE forum_monitored_forums (
    monitor_id integer DEFAULT nextval(('forum_monitored_forums_pk_seq'::text)::regclass) NOT NULL,
    forum_id integer DEFAULT 0 NOT NULL,
    user_id integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.forum_monitored_forums OWNER TO fusionforge;

--
-- Name: forum_monitored_forums_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE forum_monitored_forums_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.forum_monitored_forums_pk_seq OWNER TO fusionforge;

--
-- Name: forum_pending_attachment; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE forum_pending_attachment (
    attachmentid integer NOT NULL,
    userid integer DEFAULT 100 NOT NULL,
    dateline integer DEFAULT 0 NOT NULL,
    filename character varying(100) DEFAULT ''::character varying NOT NULL,
    filedata text NOT NULL,
    visible smallint DEFAULT (0)::smallint NOT NULL,
    counter smallint DEFAULT (0)::smallint NOT NULL,
    filesize integer DEFAULT 0 NOT NULL,
    msg_id integer DEFAULT 0 NOT NULL,
    filehash character varying(32) DEFAULT ''::character varying NOT NULL,
    mimetype character varying(32) DEFAULT 'application/octet-stream'::character varying NOT NULL
);


ALTER TABLE public.forum_pending_attachment OWNER TO fusionforge;

--
-- Name: forum_pending_attachment_attachmentid_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE forum_pending_attachment_attachmentid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.forum_pending_attachment_attachmentid_seq OWNER TO fusionforge;

--
-- Name: forum_pending_attachment_attachmentid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: fusionforge
--

ALTER SEQUENCE forum_pending_attachment_attachmentid_seq OWNED BY forum_pending_attachment.attachmentid;


--
-- Name: forum_pending_messages; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE forum_pending_messages (
    msg_id integer NOT NULL,
    group_forum_id integer DEFAULT 0 NOT NULL,
    posted_by integer DEFAULT 0 NOT NULL,
    subject text DEFAULT ''::text NOT NULL,
    body text DEFAULT ''::text NOT NULL,
    post_date integer DEFAULT 0 NOT NULL,
    is_followup_to integer DEFAULT 0 NOT NULL,
    thread_id integer DEFAULT 0 NOT NULL,
    has_followups integer DEFAULT 0,
    most_recent_date integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.forum_pending_messages OWNER TO fusionforge;

--
-- Name: forum_pending_messages_msg_id_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE forum_pending_messages_msg_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.forum_pending_messages_msg_id_seq OWNER TO fusionforge;

--
-- Name: forum_pending_messages_msg_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: fusionforge
--

ALTER SEQUENCE forum_pending_messages_msg_id_seq OWNED BY forum_pending_messages.msg_id;


--
-- Name: forum_pending_user_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW forum_pending_user_vw AS
    SELECT forum_pending_messages.msg_id, forum_pending_messages.group_forum_id, forum_pending_messages.posted_by, forum_pending_messages.subject, forum_pending_messages.body, forum_pending_messages.post_date, forum_pending_messages.is_followup_to, forum_pending_messages.thread_id, forum_pending_messages.has_followups, forum_pending_messages.most_recent_date, users.user_name, users.realname FROM forum_pending_messages, users WHERE (forum_pending_messages.posted_by = users.user_id);


ALTER TABLE public.forum_pending_user_vw OWNER TO fusionforge;

--
-- Name: forum_perm; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW forum_perm AS
    SELECT 0 AS id, role_setting.ref_id AS group_forum_id, user_group.user_id, (role_setting.value)::integer AS perm_level FROM role_setting, user_group WHERE ((user_group.role_id = role_setting.role_id) AND (role_setting.section_name = 'forum'::text));


ALTER TABLE public.forum_perm OWNER TO fusionforge;

--
-- Name: forum_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE forum_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.forum_pk_seq OWNER TO fusionforge;

--
-- Name: forum_saved_place; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE forum_saved_place (
    saved_place_id integer DEFAULT nextval(('forum_saved_place_pk_seq'::text)::regclass) NOT NULL,
    user_id integer DEFAULT 0 NOT NULL,
    forum_id integer DEFAULT 0 NOT NULL,
    save_date integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.forum_saved_place OWNER TO fusionforge;

--
-- Name: forum_saved_place_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE forum_saved_place_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.forum_saved_place_pk_seq OWNER TO fusionforge;

--
-- Name: forum_thread_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE forum_thread_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.forum_thread_seq OWNER TO fusionforge;

--
-- Name: forum_user_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW forum_user_vw AS
    SELECT forum.msg_id, forum.group_forum_id, forum.posted_by, forum.subject, forum.body, forum.post_date, forum.is_followup_to, forum.thread_id, forum.has_followups, forum.most_recent_date, users.user_name, users.realname FROM forum, users WHERE (forum.posted_by = users.user_id);


ALTER TABLE public.forum_user_vw OWNER TO fusionforge;

--
-- Name: foundry_news_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE foundry_news_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.foundry_news_pk_seq OWNER TO fusionforge;

--
-- Name: frs_dlstats_file; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE frs_dlstats_file (
    ip_address text,
    file_id integer,
    month integer,
    day integer,
    user_id integer
);


ALTER TABLE public.frs_dlstats_file OWNER TO fusionforge;

--
-- Name: frs_dlstats_file_agg_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW frs_dlstats_file_agg_vw AS
    SELECT frs_dlstats_file.month, frs_dlstats_file.day, frs_dlstats_file.file_id, count(*) AS downloads FROM frs_dlstats_file GROUP BY frs_dlstats_file.month, frs_dlstats_file.day, frs_dlstats_file.file_id;


ALTER TABLE public.frs_dlstats_file_agg_vw OWNER TO fusionforge;

--
-- Name: frs_dlstats_filetotal_agg; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE frs_dlstats_filetotal_agg (
    file_id integer NOT NULL,
    downloads integer
);


ALTER TABLE public.frs_dlstats_filetotal_agg OWNER TO fusionforge;

--
-- Name: frs_file; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE frs_file (
    file_id integer DEFAULT nextval(('frs_file_pk_seq'::text)::regclass) NOT NULL,
    filename text,
    release_id integer DEFAULT 0 NOT NULL,
    type_id integer DEFAULT 0 NOT NULL,
    processor_id integer DEFAULT 0 NOT NULL,
    release_time integer DEFAULT 0 NOT NULL,
    file_size integer DEFAULT 0 NOT NULL,
    post_date integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.frs_file OWNER TO fusionforge;

--
-- Name: frs_dlstats_group_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW frs_dlstats_group_vw AS
    SELECT frs_package.group_id, fdfa.month, fdfa.day, sum(fdfa.downloads) AS downloads FROM frs_package, frs_release, frs_file, frs_dlstats_file_agg_vw fdfa WHERE (((frs_package.package_id = frs_release.package_id) AND (frs_release.release_id = frs_file.release_id)) AND (frs_file.file_id = fdfa.file_id)) GROUP BY frs_package.group_id, fdfa.month, fdfa.day;


ALTER TABLE public.frs_dlstats_group_vw OWNER TO fusionforge;

--
-- Name: frs_dlstats_grouptotal_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW frs_dlstats_grouptotal_vw AS
    SELECT frs_package.group_id, sum(frs_dlstats_filetotal_agg.downloads) AS downloads FROM frs_package, frs_release, frs_file, frs_dlstats_filetotal_agg WHERE (((frs_package.package_id = frs_release.package_id) AND (frs_release.release_id = frs_file.release_id)) AND (frs_file.file_id = frs_dlstats_filetotal_agg.file_id)) GROUP BY frs_package.group_id;


ALTER TABLE public.frs_dlstats_grouptotal_vw OWNER TO fusionforge;

--
-- Name: frs_file_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE frs_file_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.frs_file_pk_seq OWNER TO fusionforge;

--
-- Name: frs_filetype; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE frs_filetype (
    type_id integer DEFAULT nextval(('frs_filetype_pk_seq'::text)::regclass) NOT NULL,
    name text
);


ALTER TABLE public.frs_filetype OWNER TO fusionforge;

--
-- Name: frs_processor; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE frs_processor (
    processor_id integer DEFAULT nextval(('frs_processor_pk_seq'::text)::regclass) NOT NULL,
    name text
);


ALTER TABLE public.frs_processor OWNER TO fusionforge;

--
-- Name: frs_file_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW frs_file_vw AS
    SELECT frs_file.file_id, frs_file.filename, frs_file.release_id, frs_file.type_id, frs_file.processor_id, frs_file.release_time, frs_file.file_size, frs_file.post_date, frs_filetype.name AS filetype, frs_processor.name AS processor, frs_dlstats_filetotal_agg.downloads FROM frs_filetype, frs_processor, (frs_file LEFT JOIN frs_dlstats_filetotal_agg ON ((frs_dlstats_filetotal_agg.file_id = frs_file.file_id))) WHERE ((frs_filetype.type_id = frs_file.type_id) AND (frs_processor.processor_id = frs_file.processor_id));


ALTER TABLE public.frs_file_vw OWNER TO fusionforge;

--
-- Name: frs_filetype_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE frs_filetype_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.frs_filetype_pk_seq OWNER TO fusionforge;

--
-- Name: frs_package_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE frs_package_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.frs_package_pk_seq OWNER TO fusionforge;

--
-- Name: frs_processor_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE frs_processor_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.frs_processor_pk_seq OWNER TO fusionforge;

--
-- Name: frs_release_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE frs_release_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.frs_release_pk_seq OWNER TO fusionforge;

--
-- Name: frs_status; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE frs_status (
    status_id integer DEFAULT nextval(('frs_status_pk_seq'::text)::regclass) NOT NULL,
    name text
);


ALTER TABLE public.frs_status OWNER TO fusionforge;

--
-- Name: frs_status_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE frs_status_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.frs_status_pk_seq OWNER TO fusionforge;

--
-- Name: group_activity_monitor; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE group_activity_monitor (
    group_id integer NOT NULL,
    user_id integer NOT NULL,
    filter text
);


ALTER TABLE public.group_activity_monitor OWNER TO fusionforge;

--
-- Name: group_cvs_history; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE group_cvs_history (
    id integer DEFAULT nextval(('"group_cvs_history_id_seq"'::text)::regclass) NOT NULL,
    group_id integer DEFAULT 0 NOT NULL,
    user_name character varying(80) DEFAULT ''::character varying NOT NULL,
    cvs_commits integer DEFAULT 0 NOT NULL,
    cvs_commits_wk integer DEFAULT 0 NOT NULL,
    cvs_adds integer DEFAULT 0 NOT NULL,
    cvs_adds_wk integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.group_cvs_history OWNER TO fusionforge;

--
-- Name: group_cvs_history_id_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE group_cvs_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.group_cvs_history_id_seq OWNER TO fusionforge;

--
-- Name: group_history; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE group_history (
    group_history_id integer DEFAULT nextval(('group_history_pk_seq'::text)::regclass) NOT NULL,
    group_id integer DEFAULT 0 NOT NULL,
    field_name text DEFAULT ''::text NOT NULL,
    old_value text DEFAULT ''::text NOT NULL,
    mod_by integer DEFAULT 0 NOT NULL,
    adddate integer
);


ALTER TABLE public.group_history OWNER TO fusionforge;

--
-- Name: group_history_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE group_history_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.group_history_pk_seq OWNER TO fusionforge;

--
-- Name: group_join_request; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE group_join_request (
    group_id integer NOT NULL,
    user_id integer NOT NULL,
    comments text,
    request_date integer
);


ALTER TABLE public.group_join_request OWNER TO fusionforge;

--
-- Name: group_plugin; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE group_plugin (
    group_plugin_id integer DEFAULT nextval(('group_plugin_pk_seq'::text)::regclass) NOT NULL,
    group_id integer,
    plugin_id integer
);


ALTER TABLE public.group_plugin OWNER TO fusionforge;

--
-- Name: group_plugin_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE group_plugin_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.group_plugin_pk_seq OWNER TO fusionforge;

--
-- Name: groups; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE groups (
    group_id integer DEFAULT nextval(('groups_pk_seq'::text)::regclass) NOT NULL,
    group_name character varying(40),
    homepage character varying(128),
    is_public integer DEFAULT 0 NOT NULL,
    status character(1) DEFAULT 'A'::bpchar NOT NULL,
    unix_group_name character varying(30) DEFAULT ''::character varying NOT NULL,
    unix_box character varying(20) DEFAULT 'shell1'::character varying NOT NULL,
    http_domain character varying(80),
    short_description character varying(255),
    register_purpose text,
    license_other text,
    register_time integer DEFAULT 0 NOT NULL,
    rand_hash text,
    use_mail integer DEFAULT 1 NOT NULL,
    use_survey integer DEFAULT 1 NOT NULL,
    use_forum integer DEFAULT 1 NOT NULL,
    use_pm integer DEFAULT 1 NOT NULL,
    use_scm integer DEFAULT 1 NOT NULL,
    use_news integer DEFAULT 1 NOT NULL,
    type_id integer DEFAULT 1 NOT NULL,
    use_docman integer DEFAULT 1 NOT NULL,
    new_doc_address text DEFAULT ''::text NOT NULL,
    send_all_docs integer DEFAULT 0 NOT NULL,
    use_pm_depend_box integer DEFAULT 1 NOT NULL,
    use_ftp integer DEFAULT 1,
    use_tracker integer DEFAULT 1,
    use_frs integer DEFAULT 1,
    use_stats integer DEFAULT 1,
    enable_pserver integer DEFAULT 1,
    enable_anonscm integer DEFAULT 1,
    license integer DEFAULT 100,
    scm_box text
);


ALTER TABLE public.groups OWNER TO fusionforge;

--
-- Name: groups_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE groups_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.groups_pk_seq OWNER TO fusionforge;

--
-- Name: licenses; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE licenses (
    license_id integer NOT NULL,
    license_name text
);


ALTER TABLE public.licenses OWNER TO fusionforge;

--
-- Name: licenses_license_id_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE licenses_license_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.licenses_license_id_seq OWNER TO fusionforge;

--
-- Name: licenses_license_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: fusionforge
--

ALTER SEQUENCE licenses_license_id_seq OWNED BY licenses.license_id;


--
-- Name: mail_group_list; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE mail_group_list (
    group_list_id integer DEFAULT nextval(('mail_group_list_pk_seq'::text)::regclass) NOT NULL,
    group_id integer DEFAULT 0 NOT NULL,
    list_name text,
    is_public integer DEFAULT 0 NOT NULL,
    password character varying(16),
    list_admin integer DEFAULT 0 NOT NULL,
    status integer DEFAULT 0 NOT NULL,
    description text
);


ALTER TABLE public.mail_group_list OWNER TO fusionforge;

--
-- Name: mail_group_list_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE mail_group_list_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.mail_group_list_pk_seq OWNER TO fusionforge;

--
-- Name: massmail_queue; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE massmail_queue (
    id integer DEFAULT nextval(('"massmail_queue_id_seq"'::text)::regclass) NOT NULL,
    type character varying(8) NOT NULL,
    subject text NOT NULL,
    message text NOT NULL,
    queued_date integer NOT NULL,
    last_userid integer DEFAULT 0 NOT NULL,
    failed_date integer DEFAULT 0 NOT NULL,
    finished_date integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.massmail_queue OWNER TO fusionforge;

--
-- Name: massmail_queue_id_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE massmail_queue_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.massmail_queue_id_seq OWNER TO fusionforge;

--
-- Name: news_bytes; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE news_bytes (
    id integer DEFAULT nextval(('news_bytes_pk_seq'::text)::regclass) NOT NULL,
    group_id integer DEFAULT 0 NOT NULL,
    submitted_by integer DEFAULT 0 NOT NULL,
    is_approved integer DEFAULT 0 NOT NULL,
    post_date integer DEFAULT 0 NOT NULL,
    forum_id integer DEFAULT 0 NOT NULL,
    summary text,
    details text
);


ALTER TABLE public.news_bytes OWNER TO fusionforge;

--
-- Name: news_bytes_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE news_bytes_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.news_bytes_pk_seq OWNER TO fusionforge;

--
-- Name: nss_groups; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE nss_groups (
    user_id integer,
    group_id integer,
    name character varying(30),
    gid integer
);


ALTER TABLE public.nss_groups OWNER TO fusionforge;

--
-- Name: nss_passwd; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW nss_passwd AS
    SELECT users.unix_uid AS uid, users.unix_gid AS gid, users.user_name AS login, users.unix_pw AS passwd, users.realname AS gecos, users.shell, users.user_name AS homedir, users.status FROM users WHERE (users.unix_status = 'A'::bpchar);


ALTER TABLE public.nss_passwd OWNER TO fusionforge;

--
-- Name: nss_shadow; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW nss_shadow AS
    SELECT users.user_name AS login, users.unix_pw AS passwd, 'n'::bpchar AS expired, 'n'::bpchar AS pwchange FROM users WHERE (users.unix_status = 'A'::bpchar);


ALTER TABLE public.nss_shadow OWNER TO fusionforge;

--
-- Name: nss_usergroups; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE nss_usergroups (
    uid integer,
    gid integer,
    user_id integer,
    group_id integer,
    user_name text,
    unix_group_name character varying
);


ALTER TABLE public.nss_usergroups OWNER TO fusionforge;

--
-- Name: people_job; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE people_job (
    job_id integer DEFAULT nextval(('people_job_pk_seq'::text)::regclass) NOT NULL,
    group_id integer DEFAULT 0 NOT NULL,
    created_by integer DEFAULT 0 NOT NULL,
    title text,
    description text,
    post_date integer DEFAULT 0 NOT NULL,
    status_id integer DEFAULT 0 NOT NULL,
    category_id integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.people_job OWNER TO fusionforge;

--
-- Name: people_job_category; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE people_job_category (
    category_id integer DEFAULT nextval(('people_job_category_pk_seq'::text)::regclass) NOT NULL,
    name text,
    private_flag integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.people_job_category OWNER TO fusionforge;

--
-- Name: people_job_category_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE people_job_category_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.people_job_category_pk_seq OWNER TO fusionforge;

--
-- Name: people_job_inventory; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE people_job_inventory (
    job_inventory_id integer DEFAULT nextval(('people_job_inventory_pk_seq'::text)::regclass) NOT NULL,
    job_id integer DEFAULT 0 NOT NULL,
    skill_id integer DEFAULT 0 NOT NULL,
    skill_level_id integer DEFAULT 0 NOT NULL,
    skill_year_id integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.people_job_inventory OWNER TO fusionforge;

--
-- Name: people_job_inventory_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE people_job_inventory_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.people_job_inventory_pk_seq OWNER TO fusionforge;

--
-- Name: people_job_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE people_job_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.people_job_pk_seq OWNER TO fusionforge;

--
-- Name: people_job_status; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE people_job_status (
    status_id integer DEFAULT nextval(('people_job_status_pk_seq'::text)::regclass) NOT NULL,
    name text
);


ALTER TABLE public.people_job_status OWNER TO fusionforge;

--
-- Name: people_job_status_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE people_job_status_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.people_job_status_pk_seq OWNER TO fusionforge;

--
-- Name: people_skill; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE people_skill (
    skill_id integer DEFAULT nextval(('people_skill_pk_seq'::text)::regclass) NOT NULL,
    name text
);


ALTER TABLE public.people_skill OWNER TO fusionforge;

--
-- Name: people_skill_inventory; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE people_skill_inventory (
    skill_inventory_id integer DEFAULT nextval(('people_skill_inventory_pk_seq'::text)::regclass) NOT NULL,
    user_id integer DEFAULT 0 NOT NULL,
    skill_id integer DEFAULT 0 NOT NULL,
    skill_level_id integer DEFAULT 0 NOT NULL,
    skill_year_id integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.people_skill_inventory OWNER TO fusionforge;

--
-- Name: people_skill_inventory_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE people_skill_inventory_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.people_skill_inventory_pk_seq OWNER TO fusionforge;

--
-- Name: people_skill_level; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE people_skill_level (
    skill_level_id integer DEFAULT nextval(('people_skill_level_pk_seq'::text)::regclass) NOT NULL,
    name text
);


ALTER TABLE public.people_skill_level OWNER TO fusionforge;

--
-- Name: people_skill_level_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE people_skill_level_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.people_skill_level_pk_seq OWNER TO fusionforge;

--
-- Name: people_skill_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE people_skill_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.people_skill_pk_seq OWNER TO fusionforge;

--
-- Name: people_skill_year; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE people_skill_year (
    skill_year_id integer DEFAULT nextval(('people_skill_year_pk_seq'::text)::regclass) NOT NULL,
    name text
);


ALTER TABLE public.people_skill_year OWNER TO fusionforge;

--
-- Name: people_skill_year_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE people_skill_year_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.people_skill_year_pk_seq OWNER TO fusionforge;

--
-- Name: plugin_cvstracker_artifact_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE plugin_cvstracker_artifact_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.plugin_cvstracker_artifact_seq OWNER TO fusionforge;

--
-- Name: plugin_cvstracker_master_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE plugin_cvstracker_master_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.plugin_cvstracker_master_seq OWNER TO fusionforge;

--
-- Name: plugins; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE plugins (
    plugin_id integer DEFAULT nextval(('plugins_pk_seq'::text)::regclass) NOT NULL,
    plugin_name character varying(32) NOT NULL,
    plugin_desc text
);


ALTER TABLE public.plugins OWNER TO fusionforge;

--
-- Name: plugins_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE plugins_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.plugins_pk_seq OWNER TO fusionforge;

--
-- Name: prdb_dbs; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE prdb_dbs (
    dbid integer DEFAULT nextval(('"prdb_dbs_dbid_seq"'::text)::regclass) NOT NULL,
    group_id integer NOT NULL,
    dbname text NOT NULL,
    dbusername text NOT NULL,
    dbuserpass text NOT NULL,
    requestdate integer NOT NULL,
    dbtype integer NOT NULL,
    created_by integer NOT NULL,
    state integer NOT NULL
);


ALTER TABLE public.prdb_dbs OWNER TO fusionforge;

--
-- Name: prdb_dbs_dbid_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE prdb_dbs_dbid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.prdb_dbs_dbid_seq OWNER TO fusionforge;

--
-- Name: prdb_states; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE prdb_states (
    stateid integer NOT NULL,
    statename text
);


ALTER TABLE public.prdb_states OWNER TO fusionforge;

--
-- Name: prdb_types; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE prdb_types (
    dbtypeid integer NOT NULL,
    dbservername text NOT NULL,
    dbsoftware text NOT NULL
);


ALTER TABLE public.prdb_types OWNER TO fusionforge;

--
-- Name: project_assigned_to; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE project_assigned_to (
    project_assigned_id integer DEFAULT nextval(('project_assigned_to_pk_seq'::text)::regclass) NOT NULL,
    project_task_id integer DEFAULT 0 NOT NULL,
    assigned_to_id integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.project_assigned_to OWNER TO fusionforge;

--
-- Name: project_assigned_to_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE project_assigned_to_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.project_assigned_to_pk_seq OWNER TO fusionforge;

--
-- Name: project_categor_category_id_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE project_categor_category_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.project_categor_category_id_seq OWNER TO fusionforge;

--
-- Name: project_category; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE project_category (
    category_id integer DEFAULT nextval(('"project_categor_category_id_seq"'::text)::regclass) NOT NULL,
    group_project_id integer,
    category_name text
);


ALTER TABLE public.project_category OWNER TO fusionforge;

--
-- Name: project_counts_agg; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE project_counts_agg (
    group_project_id integer NOT NULL,
    count integer DEFAULT 0 NOT NULL,
    open_count integer DEFAULT 0
);


ALTER TABLE public.project_counts_agg OWNER TO fusionforge;

--
-- Name: project_dependencies; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE project_dependencies (
    project_depend_id integer DEFAULT nextval(('project_dependencies_pk_seq'::text)::regclass) NOT NULL,
    project_task_id integer DEFAULT 0 NOT NULL,
    is_dependent_on_task_id integer DEFAULT 0 NOT NULL,
    link_type character(2) DEFAULT 'FS'::bpchar
);


ALTER TABLE public.project_dependencies OWNER TO fusionforge;

--
-- Name: project_task; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE project_task (
    project_task_id integer DEFAULT nextval(('project_task_pk_seq'::text)::regclass) NOT NULL,
    group_project_id integer DEFAULT 0 NOT NULL,
    summary text DEFAULT ''::text NOT NULL,
    details text DEFAULT ''::text NOT NULL,
    percent_complete integer DEFAULT 0 NOT NULL,
    priority integer DEFAULT 3 NOT NULL,
    hours double precision DEFAULT (0)::double precision NOT NULL,
    start_date integer DEFAULT 0 NOT NULL,
    end_date integer DEFAULT 0 NOT NULL,
    created_by integer DEFAULT 0 NOT NULL,
    status_id integer DEFAULT 0 NOT NULL,
    category_id integer,
    duration integer DEFAULT 0,
    parent_id integer DEFAULT 0,
    last_modified_date integer
);


ALTER TABLE public.project_task OWNER TO fusionforge;

--
-- Name: project_depend_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW project_depend_vw AS
    SELECT pt.project_task_id, pd.is_dependent_on_task_id, pd.link_type, pt.end_date, pt.start_date FROM (project_task pt NATURAL JOIN project_dependencies pd);


ALTER TABLE public.project_depend_vw OWNER TO fusionforge;

--
-- Name: project_dependencies_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE project_dependencies_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.project_dependencies_pk_seq OWNER TO fusionforge;

--
-- Name: project_dependon_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW project_dependon_vw AS
    SELECT pd.project_task_id, pd.is_dependent_on_task_id, pd.link_type, pt.end_date, pt.start_date FROM (project_task pt FULL JOIN project_dependencies pd ON ((pd.is_dependent_on_task_id = pt.project_task_id)));


ALTER TABLE public.project_dependon_vw OWNER TO fusionforge;

--
-- Name: project_group_list; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE project_group_list (
    group_project_id integer DEFAULT nextval(('project_group_list_pk_seq'::text)::regclass) NOT NULL,
    group_id integer DEFAULT 0 NOT NULL,
    project_name text DEFAULT ''::text NOT NULL,
    is_public integer DEFAULT 0 NOT NULL,
    description text,
    send_all_posts_to text
);


ALTER TABLE public.project_group_list OWNER TO fusionforge;

--
-- Name: project_group_list_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE project_group_list_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.project_group_list_pk_seq OWNER TO fusionforge;

--
-- Name: project_group_list_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW project_group_list_vw AS
    SELECT project_group_list.group_project_id, project_group_list.group_id, project_group_list.project_name, project_group_list.is_public, project_group_list.description, project_group_list.send_all_posts_to, project_counts_agg.count, project_counts_agg.open_count FROM (project_group_list NATURAL JOIN project_counts_agg);


ALTER TABLE public.project_group_list_vw OWNER TO fusionforge;

--
-- Name: project_history; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE project_history (
    project_history_id integer DEFAULT nextval(('project_history_pk_seq'::text)::regclass) NOT NULL,
    project_task_id integer DEFAULT 0 NOT NULL,
    field_name text DEFAULT ''::text NOT NULL,
    old_value text DEFAULT ''::text NOT NULL,
    mod_by integer DEFAULT 0 NOT NULL,
    mod_date integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.project_history OWNER TO fusionforge;

--
-- Name: project_history_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE project_history_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.project_history_pk_seq OWNER TO fusionforge;

--
-- Name: project_history_user_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW project_history_user_vw AS
    SELECT users.realname, users.email, users.user_name, project_history.project_history_id, project_history.project_task_id, project_history.field_name, project_history.old_value, project_history.mod_by, project_history.mod_date FROM users, project_history WHERE (project_history.mod_by = users.user_id);


ALTER TABLE public.project_history_user_vw OWNER TO fusionforge;

--
-- Name: project_messa_project_messa_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE project_messa_project_messa_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.project_messa_project_messa_seq OWNER TO fusionforge;

--
-- Name: project_messages; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE project_messages (
    project_message_id integer DEFAULT nextval(('"project_messa_project_messa_seq"'::text)::regclass) NOT NULL,
    project_task_id integer NOT NULL,
    body text,
    posted_by integer NOT NULL,
    postdate integer NOT NULL
);


ALTER TABLE public.project_messages OWNER TO fusionforge;

--
-- Name: project_message_user_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW project_message_user_vw AS
    SELECT users.realname, users.email, users.user_name, project_messages.project_message_id, project_messages.project_task_id, project_messages.body, project_messages.posted_by, project_messages.postdate FROM users, project_messages WHERE (project_messages.posted_by = users.user_id);


ALTER TABLE public.project_message_user_vw OWNER TO fusionforge;

--
-- Name: project_metric; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE project_metric (
    ranking integer DEFAULT nextval(('project_metric_pk_seq'::text)::regclass) NOT NULL,
    percentile double precision,
    group_id integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.project_metric OWNER TO fusionforge;

--
-- Name: project_metric_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE project_metric_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.project_metric_pk_seq OWNER TO fusionforge;

--
-- Name: project_metric_tmp1; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE project_metric_tmp1 (
    ranking integer DEFAULT nextval(('project_metric_tmp1_pk_seq'::text)::regclass) NOT NULL,
    group_id integer DEFAULT 0 NOT NULL,
    value double precision
);


ALTER TABLE public.project_metric_tmp1 OWNER TO fusionforge;

--
-- Name: project_metric_tmp1_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE project_metric_tmp1_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.project_metric_tmp1_pk_seq OWNER TO fusionforge;

--
-- Name: project_metric_wee_ranking1_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE project_metric_wee_ranking1_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.project_metric_wee_ranking1_seq OWNER TO fusionforge;

--
-- Name: project_perm; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW project_perm AS
    SELECT 0 AS id, role_setting.ref_id AS group_project_id, user_group.user_id, (role_setting.value)::integer AS perm_level FROM role_setting, user_group WHERE ((user_group.role_id = role_setting.role_id) AND (role_setting.section_name = 'pm'::text));


ALTER TABLE public.project_perm OWNER TO fusionforge;

--
-- Name: project_status; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE project_status (
    status_id integer DEFAULT nextval(('project_status_pk_seq'::text)::regclass) NOT NULL,
    status_name text DEFAULT ''::text NOT NULL
);


ALTER TABLE public.project_status OWNER TO fusionforge;

--
-- Name: project_status_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE project_status_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.project_status_pk_seq OWNER TO fusionforge;

--
-- Name: project_sums_agg; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE project_sums_agg (
    group_id integer DEFAULT 0 NOT NULL,
    type character(4) NOT NULL,
    count integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.project_sums_agg OWNER TO fusionforge;

--
-- Name: project_tags; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE project_tags (
    group_id integer NOT NULL,
    name text NOT NULL
);


ALTER TABLE public.project_tags OWNER TO fusionforge;

--
-- Name: project_task_artifact; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE project_task_artifact (
    project_task_id integer NOT NULL,
    artifact_id integer NOT NULL
);


ALTER TABLE public.project_task_artifact OWNER TO fusionforge;

--
-- Name: project_task_external_order; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE project_task_external_order (
    project_task_id integer NOT NULL,
    external_id integer NOT NULL
);


ALTER TABLE public.project_task_external_order OWNER TO fusionforge;

--
-- Name: project_task_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE project_task_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.project_task_pk_seq OWNER TO fusionforge;

--
-- Name: project_task_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW project_task_vw AS
    SELECT project_task.project_task_id, project_task.group_project_id, project_task.summary, project_task.details, project_task.percent_complete, project_task.priority, project_task.hours, project_task.start_date, project_task.end_date, project_task.created_by, project_task.status_id, project_task.category_id, project_task.duration, project_task.parent_id, project_task.last_modified_date, project_category.category_name, project_status.status_name, users.user_name, users.realname FROM (((project_task FULL JOIN project_category ON ((project_category.category_id = project_task.category_id))) FULL JOIN users ON ((users.user_id = project_task.created_by))) NATURAL JOIN project_status);


ALTER TABLE public.project_task_vw OWNER TO fusionforge;

--
-- Name: project_weekly_metric; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE project_weekly_metric (
    ranking integer DEFAULT nextval(('project_weekly_metric_pk_seq'::text)::regclass) NOT NULL,
    percentile double precision,
    group_id integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.project_weekly_metric OWNER TO fusionforge;

--
-- Name: project_weekly_metric_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE project_weekly_metric_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.project_weekly_metric_pk_seq OWNER TO fusionforge;

--
-- Name: prweb_vhost; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE prweb_vhost (
    vhostid integer DEFAULT nextval(('"prweb_vhost_vhostid_seq"'::text)::regclass) NOT NULL,
    vhost_name text,
    docdir text,
    cgidir text,
    group_id integer NOT NULL
);


ALTER TABLE public.prweb_vhost OWNER TO fusionforge;

--
-- Name: prweb_vhost_vhostid_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE prweb_vhost_vhostid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.prweb_vhost_vhostid_seq OWNER TO fusionforge;

--
-- Name: rep_group_act_daily; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE rep_group_act_daily (
    group_id integer NOT NULL,
    day integer NOT NULL,
    tracker_opened integer NOT NULL,
    tracker_closed integer NOT NULL,
    forum integer NOT NULL,
    docs integer NOT NULL,
    downloads integer NOT NULL,
    cvs_commits integer NOT NULL,
    tasks_opened integer NOT NULL,
    tasks_closed integer NOT NULL
);


ALTER TABLE public.rep_group_act_daily OWNER TO fusionforge;

--
-- Name: rep_group_act_monthly; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE rep_group_act_monthly (
    group_id integer NOT NULL,
    month integer NOT NULL,
    tracker_opened integer NOT NULL,
    tracker_closed integer NOT NULL,
    forum integer NOT NULL,
    docs integer NOT NULL,
    downloads integer NOT NULL,
    cvs_commits integer NOT NULL,
    tasks_opened integer NOT NULL,
    tasks_closed integer NOT NULL
);


ALTER TABLE public.rep_group_act_monthly OWNER TO fusionforge;

--
-- Name: rep_group_act_oa_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW rep_group_act_oa_vw AS
    SELECT rep_group_act_monthly.group_id, sum(rep_group_act_monthly.tracker_opened) AS tracker_opened, sum(rep_group_act_monthly.tracker_closed) AS tracker_closed, sum(rep_group_act_monthly.forum) AS forum, sum(rep_group_act_monthly.docs) AS docs, sum(rep_group_act_monthly.downloads) AS downloads, sum(rep_group_act_monthly.cvs_commits) AS cvs_commits, sum(rep_group_act_monthly.tasks_opened) AS tasks_opened, sum(rep_group_act_monthly.tasks_closed) AS tasks_closed FROM rep_group_act_monthly GROUP BY rep_group_act_monthly.group_id;


ALTER TABLE public.rep_group_act_oa_vw OWNER TO fusionforge;

--
-- Name: rep_group_act_weekly; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE rep_group_act_weekly (
    group_id integer NOT NULL,
    week integer NOT NULL,
    tracker_opened integer NOT NULL,
    tracker_closed integer NOT NULL,
    forum integer NOT NULL,
    docs integer NOT NULL,
    downloads integer NOT NULL,
    cvs_commits integer NOT NULL,
    tasks_opened integer NOT NULL,
    tasks_closed integer NOT NULL
);


ALTER TABLE public.rep_group_act_weekly OWNER TO fusionforge;

--
-- Name: rep_groups_added_daily; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE rep_groups_added_daily (
    day integer NOT NULL,
    added integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.rep_groups_added_daily OWNER TO fusionforge;

--
-- Name: rep_groups_added_monthly; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE rep_groups_added_monthly (
    month integer NOT NULL,
    added integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.rep_groups_added_monthly OWNER TO fusionforge;

--
-- Name: rep_groups_added_weekly; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE rep_groups_added_weekly (
    week integer NOT NULL,
    added integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.rep_groups_added_weekly OWNER TO fusionforge;

--
-- Name: rep_groups_cum_daily; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE rep_groups_cum_daily (
    day integer NOT NULL,
    total integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.rep_groups_cum_daily OWNER TO fusionforge;

--
-- Name: rep_groups_cum_monthly; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE rep_groups_cum_monthly (
    month integer NOT NULL,
    total integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.rep_groups_cum_monthly OWNER TO fusionforge;

--
-- Name: rep_groups_cum_weekly; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE rep_groups_cum_weekly (
    week integer NOT NULL,
    total integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.rep_groups_cum_weekly OWNER TO fusionforge;

--
-- Name: rep_site_act_daily_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW rep_site_act_daily_vw AS
    SELECT rep_group_act_daily.day, sum(rep_group_act_daily.tracker_opened) AS tracker_opened, sum(rep_group_act_daily.tracker_closed) AS tracker_closed, sum(rep_group_act_daily.forum) AS forum, sum(rep_group_act_daily.docs) AS docs, sum(rep_group_act_daily.downloads) AS downloads, sum(rep_group_act_daily.cvs_commits) AS cvs_commits, sum(rep_group_act_daily.tasks_opened) AS tasks_opened, sum(rep_group_act_daily.tasks_closed) AS tasks_closed FROM rep_group_act_daily GROUP BY rep_group_act_daily.day;


ALTER TABLE public.rep_site_act_daily_vw OWNER TO fusionforge;

--
-- Name: rep_site_act_monthly_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW rep_site_act_monthly_vw AS
    SELECT rep_group_act_monthly.month, sum(rep_group_act_monthly.tracker_opened) AS tracker_opened, sum(rep_group_act_monthly.tracker_closed) AS tracker_closed, sum(rep_group_act_monthly.forum) AS forum, sum(rep_group_act_monthly.docs) AS docs, sum(rep_group_act_monthly.downloads) AS downloads, sum(rep_group_act_monthly.cvs_commits) AS cvs_commits, sum(rep_group_act_monthly.tasks_opened) AS tasks_opened, sum(rep_group_act_monthly.tasks_closed) AS tasks_closed FROM rep_group_act_monthly GROUP BY rep_group_act_monthly.month;


ALTER TABLE public.rep_site_act_monthly_vw OWNER TO fusionforge;

--
-- Name: rep_site_act_weekly_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW rep_site_act_weekly_vw AS
    SELECT rep_group_act_weekly.week, sum(rep_group_act_weekly.tracker_opened) AS tracker_opened, sum(rep_group_act_weekly.tracker_closed) AS tracker_closed, sum(rep_group_act_weekly.forum) AS forum, sum(rep_group_act_weekly.docs) AS docs, sum(rep_group_act_weekly.downloads) AS downloads, sum(rep_group_act_weekly.cvs_commits) AS cvs_commits, sum(rep_group_act_weekly.tasks_opened) AS tasks_opened, sum(rep_group_act_weekly.tasks_closed) AS tasks_closed FROM rep_group_act_weekly GROUP BY rep_group_act_weekly.week;


ALTER TABLE public.rep_site_act_weekly_vw OWNER TO fusionforge;

--
-- Name: rep_time_category; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE rep_time_category (
    time_code integer DEFAULT nextval(('rep_time_category_time_code_seq'::text)::regclass),
    category_name text
);


ALTER TABLE public.rep_time_category OWNER TO fusionforge;

--
-- Name: rep_time_category_time_code_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE rep_time_category_time_code_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.rep_time_category_time_code_seq OWNER TO fusionforge;

--
-- Name: rep_time_tracking; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE rep_time_tracking (
    week integer NOT NULL,
    report_date integer NOT NULL,
    user_id integer NOT NULL,
    project_task_id integer NOT NULL,
    time_code integer NOT NULL,
    hours double precision NOT NULL
);


ALTER TABLE public.rep_time_tracking OWNER TO fusionforge;

--
-- Name: rep_user_act_daily; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE rep_user_act_daily (
    user_id integer NOT NULL,
    day integer NOT NULL,
    tracker_opened integer NOT NULL,
    tracker_closed integer NOT NULL,
    forum integer NOT NULL,
    docs integer NOT NULL,
    cvs_commits integer NOT NULL,
    tasks_opened integer NOT NULL,
    tasks_closed integer NOT NULL
);


ALTER TABLE public.rep_user_act_daily OWNER TO fusionforge;

--
-- Name: rep_user_act_monthly; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE rep_user_act_monthly (
    user_id integer NOT NULL,
    month integer NOT NULL,
    tracker_opened integer NOT NULL,
    tracker_closed integer NOT NULL,
    forum integer NOT NULL,
    docs integer NOT NULL,
    cvs_commits integer NOT NULL,
    tasks_opened integer NOT NULL,
    tasks_closed integer NOT NULL
);


ALTER TABLE public.rep_user_act_monthly OWNER TO fusionforge;

--
-- Name: rep_user_act_oa_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW rep_user_act_oa_vw AS
    SELECT rep_user_act_monthly.user_id, sum(rep_user_act_monthly.tracker_opened) AS tracker_opened, sum(rep_user_act_monthly.tracker_closed) AS tracker_closed, sum(rep_user_act_monthly.forum) AS forum, sum(rep_user_act_monthly.docs) AS docs, sum(rep_user_act_monthly.cvs_commits) AS cvs_commits, sum(rep_user_act_monthly.tasks_opened) AS tasks_opened, sum(rep_user_act_monthly.tasks_closed) AS tasks_closed FROM rep_user_act_monthly GROUP BY rep_user_act_monthly.user_id;


ALTER TABLE public.rep_user_act_oa_vw OWNER TO fusionforge;

--
-- Name: rep_user_act_weekly; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE rep_user_act_weekly (
    user_id integer NOT NULL,
    week integer NOT NULL,
    tracker_opened integer NOT NULL,
    tracker_closed integer NOT NULL,
    forum integer NOT NULL,
    docs integer NOT NULL,
    cvs_commits integer NOT NULL,
    tasks_opened integer NOT NULL,
    tasks_closed integer NOT NULL
);


ALTER TABLE public.rep_user_act_weekly OWNER TO fusionforge;

--
-- Name: rep_users_added_daily; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE rep_users_added_daily (
    day integer NOT NULL,
    added integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.rep_users_added_daily OWNER TO fusionforge;

--
-- Name: rep_users_added_monthly; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE rep_users_added_monthly (
    month integer NOT NULL,
    added integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.rep_users_added_monthly OWNER TO fusionforge;

--
-- Name: rep_users_added_weekly; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE rep_users_added_weekly (
    week integer NOT NULL,
    added integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.rep_users_added_weekly OWNER TO fusionforge;

--
-- Name: rep_users_cum_daily; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE rep_users_cum_daily (
    day integer NOT NULL,
    total integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.rep_users_cum_daily OWNER TO fusionforge;

--
-- Name: rep_users_cum_monthly; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE rep_users_cum_monthly (
    month integer NOT NULL,
    total integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.rep_users_cum_monthly OWNER TO fusionforge;

--
-- Name: rep_users_cum_weekly; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE rep_users_cum_weekly (
    week integer NOT NULL,
    total integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.rep_users_cum_weekly OWNER TO fusionforge;

--
-- Name: role; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE role (
    role_id integer NOT NULL,
    group_id integer NOT NULL,
    role_name text
);


ALTER TABLE public.role OWNER TO fusionforge;

--
-- Name: role_role_id_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE role_role_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.role_role_id_seq OWNER TO fusionforge;

--
-- Name: role_role_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: fusionforge
--

ALTER SEQUENCE role_role_id_seq OWNED BY role.role_id;


--
-- Name: skills_data; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE skills_data (
    skills_data_id integer DEFAULT nextval(('skills_data_pk_seq'::text)::regclass) NOT NULL,
    user_id integer DEFAULT 0 NOT NULL,
    type integer DEFAULT 0 NOT NULL,
    title character varying(100) DEFAULT ''::character varying NOT NULL,
    start integer DEFAULT 0 NOT NULL,
    finish integer DEFAULT 0 NOT NULL,
    keywords character varying(255) DEFAULT ''::character varying NOT NULL
);


ALTER TABLE public.skills_data OWNER TO fusionforge;

--
-- Name: skills_data_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE skills_data_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.skills_data_pk_seq OWNER TO fusionforge;

--
-- Name: skills_data_types; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE skills_data_types (
    type_id integer DEFAULT nextval(('skills_data_types_pk_seq'::text)::regclass) NOT NULL,
    type_name character varying(25) DEFAULT ''::character varying NOT NULL
);


ALTER TABLE public.skills_data_types OWNER TO fusionforge;

--
-- Name: skills_data_types_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE skills_data_types_pk_seq
    START WITH 0
    INCREMENT BY 1
    MINVALUE 0
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.skills_data_types_pk_seq OWNER TO fusionforge;

--
-- Name: snippet; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE snippet (
    snippet_id integer DEFAULT nextval(('snippet_pk_seq'::text)::regclass) NOT NULL,
    created_by integer DEFAULT 0 NOT NULL,
    name text,
    description text,
    type integer DEFAULT 0 NOT NULL,
    language integer DEFAULT 0 NOT NULL,
    license text DEFAULT ''::text NOT NULL,
    category integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.snippet OWNER TO fusionforge;

--
-- Name: snippet_package; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE snippet_package (
    snippet_package_id integer DEFAULT nextval(('snippet_package_pk_seq'::text)::regclass) NOT NULL,
    created_by integer DEFAULT 0 NOT NULL,
    name text,
    description text,
    category integer DEFAULT 0 NOT NULL,
    language integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.snippet_package OWNER TO fusionforge;

--
-- Name: snippet_package_item; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE snippet_package_item (
    snippet_package_item_id integer DEFAULT nextval(('snippet_package_item_pk_seq'::text)::regclass) NOT NULL,
    snippet_package_version_id integer DEFAULT 0 NOT NULL,
    snippet_version_id integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.snippet_package_item OWNER TO fusionforge;

--
-- Name: snippet_package_item_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE snippet_package_item_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.snippet_package_item_pk_seq OWNER TO fusionforge;

--
-- Name: snippet_package_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE snippet_package_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.snippet_package_pk_seq OWNER TO fusionforge;

--
-- Name: snippet_package_version; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE snippet_package_version (
    snippet_package_version_id integer DEFAULT nextval(('snippet_package_version_pk_seq'::text)::regclass) NOT NULL,
    snippet_package_id integer DEFAULT 0 NOT NULL,
    changes text,
    version text,
    submitted_by integer DEFAULT 0 NOT NULL,
    post_date integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.snippet_package_version OWNER TO fusionforge;

--
-- Name: snippet_package_version_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE snippet_package_version_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.snippet_package_version_pk_seq OWNER TO fusionforge;

--
-- Name: snippet_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE snippet_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.snippet_pk_seq OWNER TO fusionforge;

--
-- Name: snippet_version; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE snippet_version (
    snippet_version_id integer DEFAULT nextval(('snippet_version_pk_seq'::text)::regclass) NOT NULL,
    snippet_id integer DEFAULT 0 NOT NULL,
    changes text,
    version text,
    submitted_by integer DEFAULT 0 NOT NULL,
    post_date integer DEFAULT 0 NOT NULL,
    code text
);


ALTER TABLE public.snippet_version OWNER TO fusionforge;

--
-- Name: snippet_version_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE snippet_version_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.snippet_version_pk_seq OWNER TO fusionforge;

--
-- Name: stats_agg_logo_by_day; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE stats_agg_logo_by_day (
    day integer,
    count integer
);


ALTER TABLE public.stats_agg_logo_by_day OWNER TO fusionforge;

--
-- Name: stats_agg_logo_by_group; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE stats_agg_logo_by_group (
    month integer,
    day integer,
    group_id integer,
    count integer
);


ALTER TABLE public.stats_agg_logo_by_group OWNER TO fusionforge;

--
-- Name: stats_agg_pages_by_day; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE stats_agg_pages_by_day (
    day integer DEFAULT 0 NOT NULL,
    count integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.stats_agg_pages_by_day OWNER TO fusionforge;

--
-- Name: stats_agg_site_by_group; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE stats_agg_site_by_group (
    month integer,
    day integer,
    group_id integer,
    count integer
);


ALTER TABLE public.stats_agg_site_by_group OWNER TO fusionforge;

--
-- Name: stats_cvs_group; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE stats_cvs_group (
    month integer DEFAULT 0 NOT NULL,
    day integer DEFAULT 0 NOT NULL,
    group_id integer DEFAULT 0 NOT NULL,
    checkouts integer DEFAULT 0 NOT NULL,
    commits integer DEFAULT 0 NOT NULL,
    adds integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.stats_cvs_group OWNER TO fusionforge;

--
-- Name: stats_cvs_user; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE stats_cvs_user (
    month integer DEFAULT 0 NOT NULL,
    day integer DEFAULT 0 NOT NULL,
    group_id integer DEFAULT 0 NOT NULL,
    user_id integer DEFAULT 0 NOT NULL,
    checkouts integer DEFAULT 0 NOT NULL,
    commits integer DEFAULT 0 NOT NULL,
    adds integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.stats_cvs_user OWNER TO fusionforge;

--
-- Name: stats_project; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE stats_project (
    month integer DEFAULT 0 NOT NULL,
    day integer DEFAULT 0 NOT NULL,
    group_id integer DEFAULT 0 NOT NULL,
    file_releases integer DEFAULT 0,
    msg_posted integer DEFAULT 0,
    msg_uniq_auth integer DEFAULT 0,
    bugs_opened integer DEFAULT 0,
    bugs_closed integer DEFAULT 0,
    support_opened integer DEFAULT 0,
    support_closed integer DEFAULT 0,
    patches_opened integer DEFAULT 0,
    patches_closed integer DEFAULT 0,
    artifacts_opened integer DEFAULT 0,
    artifacts_closed integer DEFAULT 0,
    tasks_opened integer DEFAULT 0,
    tasks_closed integer DEFAULT 0,
    help_requests integer DEFAULT 0
);


ALTER TABLE public.stats_project OWNER TO fusionforge;

--
-- Name: stats_project_months; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE stats_project_months (
    month integer,
    group_id integer,
    developers integer,
    group_ranking integer,
    group_metric double precision,
    logo_showings integer,
    downloads integer,
    site_views integer,
    subdomain_views integer,
    page_views integer,
    file_releases integer,
    msg_posted integer,
    msg_uniq_auth integer,
    bugs_opened integer,
    bugs_closed integer,
    support_opened integer,
    support_closed integer,
    patches_opened integer,
    patches_closed integer,
    artifacts_opened integer,
    artifacts_closed integer,
    tasks_opened integer,
    tasks_closed integer,
    help_requests integer,
    cvs_checkouts integer,
    cvs_commits integer,
    cvs_adds integer
);


ALTER TABLE public.stats_project_months OWNER TO fusionforge;

--
-- Name: stats_project_all_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW stats_project_all_vw AS
    SELECT stats_project_months.group_id, int4(avg(stats_project_months.developers)) AS developers, int4(avg(stats_project_months.group_ranking)) AS group_ranking, avg(stats_project_months.group_metric) AS group_metric, sum(stats_project_months.logo_showings) AS logo_showings, sum(stats_project_months.downloads) AS downloads, sum(stats_project_months.site_views) AS site_views, sum(stats_project_months.subdomain_views) AS subdomain_views, sum(stats_project_months.page_views) AS page_views, sum(stats_project_months.file_releases) AS file_releases, sum(stats_project_months.msg_posted) AS msg_posted, int4(avg(stats_project_months.msg_uniq_auth)) AS msg_uniq_auth, sum(stats_project_months.bugs_opened) AS bugs_opened, sum(stats_project_months.bugs_closed) AS bugs_closed, sum(stats_project_months.support_opened) AS support_opened, sum(stats_project_months.support_closed) AS support_closed, sum(stats_project_months.patches_opened) AS patches_opened, sum(stats_project_months.patches_closed) AS patches_closed, sum(stats_project_months.artifacts_opened) AS artifacts_opened, sum(stats_project_months.artifacts_closed) AS artifacts_closed, sum(stats_project_months.tasks_opened) AS tasks_opened, sum(stats_project_months.tasks_closed) AS tasks_closed, sum(stats_project_months.help_requests) AS help_requests, sum(stats_project_months.cvs_checkouts) AS cvs_checkouts, sum(stats_project_months.cvs_commits) AS cvs_commits, sum(stats_project_months.cvs_adds) AS cvs_adds FROM stats_project_months GROUP BY stats_project_months.group_id;


ALTER TABLE public.stats_project_all_vw OWNER TO fusionforge;

--
-- Name: stats_project_developers; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE stats_project_developers (
    month integer DEFAULT 0 NOT NULL,
    day integer DEFAULT 0 NOT NULL,
    group_id integer DEFAULT 0 NOT NULL,
    developers integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.stats_project_developers OWNER TO fusionforge;

--
-- Name: stats_project_metric; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE stats_project_metric (
    month integer DEFAULT 0 NOT NULL,
    day integer DEFAULT 0 NOT NULL,
    ranking integer DEFAULT 0 NOT NULL,
    percentile double precision DEFAULT 0 NOT NULL,
    group_id integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.stats_project_metric OWNER TO fusionforge;

--
-- Name: stats_subd_pages; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE stats_subd_pages (
    month integer DEFAULT 0 NOT NULL,
    day integer DEFAULT 0 NOT NULL,
    group_id integer DEFAULT 0 NOT NULL,
    pages integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.stats_subd_pages OWNER TO fusionforge;

--
-- Name: stats_project_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW stats_project_vw AS
    SELECT spd.group_id, spd.month, spd.day, spd.developers, spm.ranking AS group_ranking, spm.percentile AS group_metric, salbg.count AS logo_showings, fdga.downloads, sasbg.count AS site_views, ssp.pages AS subdomain_views, (CASE WHEN (sasbg.count IS NOT NULL) THEN sasbg.count WHEN (0 IS NOT NULL) THEN 0 ELSE NULL::integer END + CASE WHEN (ssp.pages IS NOT NULL) THEN ssp.pages WHEN (0 IS NOT NULL) THEN 0 ELSE NULL::integer END) AS page_views, sp.file_releases, sp.msg_posted, sp.msg_uniq_auth, sp.bugs_opened, sp.bugs_closed, sp.support_opened, sp.support_closed, sp.patches_opened, sp.patches_closed, sp.artifacts_opened, sp.artifacts_closed, sp.tasks_opened, sp.tasks_closed, sp.help_requests, scg.checkouts AS cvs_checkouts, scg.commits AS cvs_commits, scg.adds AS cvs_adds FROM (((((((stats_project_developers spd LEFT JOIN stats_project sp USING (month, day, group_id)) LEFT JOIN stats_project_metric spm USING (month, day, group_id)) LEFT JOIN stats_cvs_group scg USING (month, day, group_id)) LEFT JOIN stats_agg_site_by_group sasbg USING (month, day, group_id)) LEFT JOIN stats_agg_logo_by_group salbg USING (month, day, group_id)) LEFT JOIN stats_subd_pages ssp USING (month, day, group_id)) LEFT JOIN frs_dlstats_group_vw fdga USING (month, day, group_id));


ALTER TABLE public.stats_project_vw OWNER TO fusionforge;

--
-- Name: stats_site; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE stats_site (
    month integer,
    day integer,
    uniq_users integer,
    sessions integer,
    total_users integer,
    new_users integer,
    new_projects integer
);


ALTER TABLE public.stats_site OWNER TO fusionforge;

--
-- Name: stats_site_months; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE stats_site_months (
    month integer,
    site_page_views integer,
    downloads integer,
    subdomain_views integer,
    msg_posted integer,
    bugs_opened integer,
    bugs_closed integer,
    support_opened integer,
    support_closed integer,
    patches_opened integer,
    patches_closed integer,
    artifacts_opened integer,
    artifacts_closed integer,
    tasks_opened integer,
    tasks_closed integer,
    help_requests integer,
    cvs_checkouts integer,
    cvs_commits integer,
    cvs_adds integer
);


ALTER TABLE public.stats_site_months OWNER TO fusionforge;

--
-- Name: stats_site_all_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW stats_site_all_vw AS
    SELECT sum(stats_site_months.site_page_views) AS site_page_views, sum(stats_site_months.downloads) AS downloads, sum(stats_site_months.subdomain_views) AS subdomain_views, sum(stats_site_months.msg_posted) AS msg_posted, sum(stats_site_months.bugs_opened) AS bugs_opened, sum(stats_site_months.bugs_closed) AS bugs_closed, sum(stats_site_months.support_opened) AS support_opened, sum(stats_site_months.support_closed) AS support_closed, sum(stats_site_months.patches_opened) AS patches_opened, sum(stats_site_months.patches_closed) AS patches_closed, sum(stats_site_months.artifacts_opened) AS artifacts_opened, sum(stats_site_months.artifacts_closed) AS artifacts_closed, sum(stats_site_months.tasks_opened) AS tasks_opened, sum(stats_site_months.tasks_closed) AS tasks_closed, sum(stats_site_months.help_requests) AS help_requests, sum(stats_site_months.cvs_checkouts) AS cvs_checkouts, sum(stats_site_months.cvs_commits) AS cvs_commits, sum(stats_site_months.cvs_adds) AS cvs_adds FROM stats_site_months;


ALTER TABLE public.stats_site_all_vw OWNER TO fusionforge;

--
-- Name: stats_site_pages_by_day; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE stats_site_pages_by_day (
    month integer,
    day integer,
    site_page_views integer
);


ALTER TABLE public.stats_site_pages_by_day OWNER TO fusionforge;

--
-- Name: stats_site_pages_by_month; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE stats_site_pages_by_month (
    month integer,
    site_page_views integer
);


ALTER TABLE public.stats_site_pages_by_month OWNER TO fusionforge;

--
-- Name: stats_site_vw; Type: VIEW; Schema: public; Owner: fusionforge
--

CREATE VIEW stats_site_vw AS
    SELECT p.month, p.day, sspbd.site_page_views, sum(p.downloads) AS downloads, sum(p.subdomain_views) AS subdomain_views, sum(p.msg_posted) AS msg_posted, sum(p.bugs_opened) AS bugs_opened, sum(p.bugs_closed) AS bugs_closed, sum(p.support_opened) AS support_opened, sum(p.support_closed) AS support_closed, sum(p.patches_opened) AS patches_opened, sum(p.patches_closed) AS patches_closed, sum(p.artifacts_opened) AS artifacts_opened, sum(p.artifacts_closed) AS artifacts_closed, sum(p.tasks_opened) AS tasks_opened, sum(p.tasks_closed) AS tasks_closed, sum(p.help_requests) AS help_requests, sum(p.cvs_checkouts) AS cvs_checkouts, sum(p.cvs_commits) AS cvs_commits, sum(p.cvs_adds) AS cvs_adds FROM stats_project_vw p, stats_site_pages_by_day sspbd WHERE ((p.month = sspbd.month) AND (p.day = sspbd.day)) GROUP BY p.month, p.day, sspbd.site_page_views;


ALTER TABLE public.stats_site_vw OWNER TO fusionforge;

--
-- Name: supported_langu_language_id_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE supported_langu_language_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.supported_langu_language_id_seq OWNER TO fusionforge;

--
-- Name: survey_question_types; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE survey_question_types (
    id integer DEFAULT nextval(('survey_question_types_pk_seq'::text)::regclass) NOT NULL,
    type text DEFAULT ''::text NOT NULL
);


ALTER TABLE public.survey_question_types OWNER TO fusionforge;

--
-- Name: survey_question_types_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE survey_question_types_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.survey_question_types_pk_seq OWNER TO fusionforge;

--
-- Name: survey_questions; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE survey_questions (
    question_id integer DEFAULT nextval(('survey_questions_pk_seq'::text)::regclass) NOT NULL,
    group_id integer DEFAULT 0 NOT NULL,
    question text DEFAULT ''::text NOT NULL,
    question_type integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.survey_questions OWNER TO fusionforge;

--
-- Name: survey_questions_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE survey_questions_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.survey_questions_pk_seq OWNER TO fusionforge;

--
-- Name: survey_rating_aggregate; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE survey_rating_aggregate (
    type integer DEFAULT 0 NOT NULL,
    id integer DEFAULT 0 NOT NULL,
    response double precision DEFAULT (0)::double precision NOT NULL,
    count integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.survey_rating_aggregate OWNER TO fusionforge;

--
-- Name: survey_rating_response; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE survey_rating_response (
    user_id integer DEFAULT 0 NOT NULL,
    type integer DEFAULT 0 NOT NULL,
    id integer DEFAULT 0 NOT NULL,
    response integer DEFAULT 0 NOT NULL,
    post_date integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.survey_rating_response OWNER TO fusionforge;

--
-- Name: survey_responses; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE survey_responses (
    user_id integer DEFAULT 0 NOT NULL,
    group_id integer DEFAULT 0 NOT NULL,
    survey_id integer DEFAULT 0 NOT NULL,
    question_id integer DEFAULT 0 NOT NULL,
    response text DEFAULT ''::text NOT NULL,
    post_date integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.survey_responses OWNER TO fusionforge;

--
-- Name: surveys; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE surveys (
    survey_id integer DEFAULT nextval(('surveys_pk_seq'::text)::regclass) NOT NULL,
    group_id integer DEFAULT 0 NOT NULL,
    survey_title text DEFAULT ''::text NOT NULL,
    survey_questions text DEFAULT ''::text NOT NULL,
    is_active integer DEFAULT 1 NOT NULL
);


ALTER TABLE public.surveys OWNER TO fusionforge;

--
-- Name: surveys_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE surveys_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.surveys_pk_seq OWNER TO fusionforge;

--
-- Name: themes; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE themes (
    theme_id integer DEFAULT nextval(('"themes_theme_id_seq"'::text)::regclass) NOT NULL,
    dirname character varying(80),
    fullname character varying(80),
    enabled boolean DEFAULT true
);


ALTER TABLE public.themes OWNER TO fusionforge;

--
-- Name: themes_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE themes_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.themes_pk_seq OWNER TO fusionforge;

--
-- Name: themes_theme_id_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE themes_theme_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.themes_theme_id_seq OWNER TO fusionforge;

--
-- Name: trove_agg; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE trove_agg (
    trove_cat_id integer,
    group_id integer,
    group_name character varying(40),
    unix_group_name character varying(30),
    status character(1),
    register_time integer,
    short_description character varying(255),
    percentile double precision,
    ranking integer
);


ALTER TABLE public.trove_agg OWNER TO fusionforge;

--
-- Name: trove_cat; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE trove_cat (
    trove_cat_id integer DEFAULT nextval(('trove_cat_pk_seq'::text)::regclass) NOT NULL,
    version integer DEFAULT 0 NOT NULL,
    parent integer DEFAULT 0 NOT NULL,
    root_parent integer DEFAULT 0 NOT NULL,
    shortname character varying(80),
    fullname character varying(80),
    description character varying(255),
    count_subcat integer DEFAULT 0 NOT NULL,
    count_subproj integer DEFAULT 0 NOT NULL,
    fullpath text DEFAULT ''::text NOT NULL,
    fullpath_ids text
);


ALTER TABLE public.trove_cat OWNER TO fusionforge;

--
-- Name: trove_cat_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE trove_cat_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.trove_cat_pk_seq OWNER TO fusionforge;

--
-- Name: trove_group_link; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE trove_group_link (
    trove_group_id integer DEFAULT nextval(('trove_group_link_pk_seq'::text)::regclass) NOT NULL,
    trove_cat_id integer DEFAULT 0 NOT NULL,
    trove_cat_version integer DEFAULT 0 NOT NULL,
    group_id integer DEFAULT 0 NOT NULL,
    trove_cat_root integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.trove_group_link OWNER TO fusionforge;

--
-- Name: trove_group_link_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE trove_group_link_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.trove_group_link_pk_seq OWNER TO fusionforge;

--
-- Name: trove_treesum_trove_treesum_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE trove_treesum_trove_treesum_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.trove_treesum_trove_treesum_seq OWNER TO fusionforge;

--
-- Name: trove_treesums; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE trove_treesums (
    trove_treesums_id integer DEFAULT nextval(('"trove_treesum_trove_treesum_seq"'::text)::regclass) NOT NULL,
    trove_cat_id integer DEFAULT 0 NOT NULL,
    limit_1 integer DEFAULT 0 NOT NULL,
    subprojects integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.trove_treesums OWNER TO fusionforge;

--
-- Name: trove_treesums_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE trove_treesums_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.trove_treesums_pk_seq OWNER TO fusionforge;

--
-- Name: unix_uid_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE unix_uid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.unix_uid_seq OWNER TO fusionforge;

--
-- Name: user_bookmarks; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE user_bookmarks (
    bookmark_id integer DEFAULT nextval(('user_bookmarks_pk_seq'::text)::regclass) NOT NULL,
    user_id integer DEFAULT 0 NOT NULL,
    bookmark_url text,
    bookmark_title text
);


ALTER TABLE public.user_bookmarks OWNER TO fusionforge;

--
-- Name: user_bookmarks_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE user_bookmarks_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.user_bookmarks_pk_seq OWNER TO fusionforge;

--
-- Name: user_diary; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE user_diary (
    id integer DEFAULT nextval(('user_diary_pk_seq'::text)::regclass) NOT NULL,
    user_id integer DEFAULT 0 NOT NULL,
    date_posted integer DEFAULT 0 NOT NULL,
    summary text,
    details text,
    is_public integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.user_diary OWNER TO fusionforge;

--
-- Name: user_diary_monitor; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE user_diary_monitor (
    monitor_id integer DEFAULT nextval(('user_diary_monitor_pk_seq'::text)::regclass) NOT NULL,
    monitored_user integer DEFAULT 0 NOT NULL,
    user_id integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.user_diary_monitor OWNER TO fusionforge;

--
-- Name: user_diary_monitor_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE user_diary_monitor_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.user_diary_monitor_pk_seq OWNER TO fusionforge;

--
-- Name: user_diary_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE user_diary_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.user_diary_pk_seq OWNER TO fusionforge;

--
-- Name: user_group_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE user_group_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.user_group_pk_seq OWNER TO fusionforge;

--
-- Name: user_metric; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE user_metric (
    ranking integer DEFAULT nextval(('user_metric_pk_seq'::text)::regclass) NOT NULL,
    user_id integer DEFAULT 0 NOT NULL,
    times_ranked integer DEFAULT 0 NOT NULL,
    avg_raters_importance double precision DEFAULT (0)::double precision NOT NULL,
    avg_rating double precision DEFAULT (0)::double precision NOT NULL,
    metric double precision DEFAULT (0)::double precision NOT NULL,
    percentile double precision DEFAULT (0)::double precision NOT NULL,
    importance_factor double precision DEFAULT (0)::double precision NOT NULL
);


ALTER TABLE public.user_metric OWNER TO fusionforge;

--
-- Name: user_metric0; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE user_metric0 (
    ranking integer DEFAULT nextval(('user_metric0_pk_seq'::text)::regclass) NOT NULL,
    user_id integer DEFAULT 0 NOT NULL,
    times_ranked integer DEFAULT 0 NOT NULL,
    avg_raters_importance double precision DEFAULT (0)::double precision NOT NULL,
    avg_rating double precision DEFAULT (0)::double precision NOT NULL,
    metric double precision DEFAULT (0)::double precision NOT NULL,
    percentile double precision DEFAULT (0)::double precision NOT NULL,
    importance_factor double precision DEFAULT (0)::double precision NOT NULL
);


ALTER TABLE public.user_metric0 OWNER TO fusionforge;

--
-- Name: user_metric0_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE user_metric0_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.user_metric0_pk_seq OWNER TO fusionforge;

--
-- Name: user_metric_history; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE user_metric_history (
    month integer NOT NULL,
    day integer NOT NULL,
    user_id integer NOT NULL,
    ranking integer NOT NULL,
    metric double precision NOT NULL
);


ALTER TABLE public.user_metric_history OWNER TO fusionforge;

--
-- Name: user_metric_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE user_metric_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.user_metric_pk_seq OWNER TO fusionforge;

--
-- Name: user_plugin; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE user_plugin (
    user_plugin_id integer DEFAULT nextval(('user_plugin_pk_seq'::text)::regclass) NOT NULL,
    user_id integer NOT NULL,
    plugin_id integer NOT NULL
);


ALTER TABLE public.user_plugin OWNER TO fusionforge;

--
-- Name: user_plugin_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE user_plugin_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.user_plugin_pk_seq OWNER TO fusionforge;

--
-- Name: user_preferences; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE user_preferences (
    user_id integer DEFAULT 0 NOT NULL,
    preference_name character varying(20) NOT NULL,
    dead1 character varying(20),
    set_date integer DEFAULT 0 NOT NULL,
    preference_value text
);


ALTER TABLE public.user_preferences OWNER TO fusionforge;

--
-- Name: user_ratings; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE user_ratings (
    rated_by integer DEFAULT 0 NOT NULL,
    user_id integer DEFAULT 0 NOT NULL,
    rate_field integer DEFAULT 0 NOT NULL,
    rating integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.user_ratings OWNER TO fusionforge;

--
-- Name: user_session; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE user_session (
    user_id integer DEFAULT 0 NOT NULL,
    session_hash character(32) DEFAULT ''::bpchar NOT NULL,
    ip_addr character(15) DEFAULT ''::bpchar NOT NULL,
    "time" integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.user_session OWNER TO fusionforge;

--
-- Name: user_type; Type: TABLE; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE TABLE user_type (
    type_id integer NOT NULL,
    type_name text
);


ALTER TABLE public.user_type OWNER TO fusionforge;

--
-- Name: user_type_type_id_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE user_type_type_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.user_type_type_id_seq OWNER TO fusionforge;

--
-- Name: user_type_type_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: fusionforge
--

ALTER SEQUENCE user_type_type_id_seq OWNED BY user_type.type_id;


--
-- Name: users_pk_seq; Type: SEQUENCE; Schema: public; Owner: fusionforge
--

CREATE SEQUENCE users_pk_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    MAXVALUE 2147483647
    CACHE 1;


ALTER TABLE public.users_pk_seq OWNER TO fusionforge;

--
-- Name: artifact_query_id; Type: DEFAULT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY artifact_query ALTER COLUMN artifact_query_id SET DEFAULT nextval('artifact_query_artifact_query_id_seq'::regclass);


--
-- Name: key_id; Type: DEFAULT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY form_keys ALTER COLUMN key_id SET DEFAULT nextval('form_keys_key_id_seq'::regclass);


--
-- Name: attachmentid; Type: DEFAULT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY forum_attachment ALTER COLUMN attachmentid SET DEFAULT nextval('forum_attachment_attachmentid_seq'::regclass);


--
-- Name: attachmentid; Type: DEFAULT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY forum_pending_attachment ALTER COLUMN attachmentid SET DEFAULT nextval('forum_pending_attachment_attachmentid_seq'::regclass);


--
-- Name: msg_id; Type: DEFAULT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY forum_pending_messages ALTER COLUMN msg_id SET DEFAULT nextval('forum_pending_messages_msg_id_seq'::regclass);


--
-- Name: license_id; Type: DEFAULT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY licenses ALTER COLUMN license_id SET DEFAULT nextval('licenses_license_id_seq'::regclass);


--
-- Name: role_id; Type: DEFAULT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY role ALTER COLUMN role_id SET DEFAULT nextval('role_role_id_seq'::regclass);


--
-- Name: type_id; Type: DEFAULT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY user_type ALTER COLUMN type_id SET DEFAULT nextval('user_type_type_id_seq'::regclass);


--
-- Data for Name: activity_log; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY activity_log (day, hour, group_id, browser, ver, platform, "time", page, type) FROM stdin;
\.


--
-- Data for Name: artifact; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY artifact (artifact_id, group_artifact_id, status_id, priority, submitted_by, assigned_to, open_date, close_date, summary, details, last_modified_date) FROM stdin;
\.


--
-- Name: artifact_artifact_id_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('artifact_artifact_id_seq', 1, false);


--
-- Name: artifact_canned_response_id_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('artifact_canned_response_id_seq', 1, false);


--
-- Data for Name: artifact_canned_responses; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY artifact_canned_responses (id, group_artifact_id, title, body) FROM stdin;
\.


--
-- Data for Name: artifact_counts_agg; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY artifact_counts_agg (group_artifact_id, count, open_count) FROM stdin;
100	0	0
\.


--
-- Data for Name: artifact_extra_field_data; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY artifact_extra_field_data (data_id, artifact_id, field_data, extra_field_id) FROM stdin;
\.


--
-- Name: artifact_extra_field_data_data_id_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('artifact_extra_field_data_data_id_seq', 1, false);


--
-- Data for Name: artifact_extra_field_elements; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY artifact_extra_field_elements (element_id, extra_field_id, element_name, status_id, element_pos) FROM stdin;
\.


--
-- Name: artifact_extra_field_elements_element_id_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('artifact_extra_field_elements_element_id_seq', 1, false);


--
-- Data for Name: artifact_extra_field_list; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY artifact_extra_field_list (extra_field_id, group_artifact_id, field_name, field_type, attribute1, attribute2, is_required, alias) FROM stdin;
\.


--
-- Name: artifact_extra_field_list_extra_field_id_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('artifact_extra_field_list_extra_field_id_seq', 1, false);


--
-- Data for Name: artifact_file; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY artifact_file (id, artifact_id, description, bin_data, filename, filesize, filetype, adddate, submitted_by) FROM stdin;
\.


--
-- Name: artifact_file_id_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('artifact_file_id_seq', 1, false);


--
-- Name: artifact_grou_group_artifac_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('artifact_grou_group_artifac_seq', 100, true);


--
-- Data for Name: artifact_group_list; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY artifact_group_list (group_artifact_id, group_id, name, description, is_public, allow_anon, email_all_updates, email_address, due_period, submit_instructions, browse_instructions, datatype, status_timeout, custom_status_field, custom_renderer, browse_list) FROM stdin;
100	1	Default	Default Data - Dont Edit	3	0	0		2592000	\N	\N	0	\N	0	\N	summary,open_date,assigned_to,submitted_by
\.


--
-- Data for Name: artifact_history; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY artifact_history (id, artifact_id, field_name, old_value, mod_by, entrydate) FROM stdin;
\.


--
-- Name: artifact_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('artifact_history_id_seq', 1, false);


--
-- Data for Name: artifact_message; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY artifact_message (id, artifact_id, submitted_by, from_email, adddate, body) FROM stdin;
\.


--
-- Name: artifact_message_id_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('artifact_message_id_seq', 1, false);


--
-- Data for Name: artifact_monitor; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY artifact_monitor (id, artifact_id, user_id, email) FROM stdin;
\.


--
-- Name: artifact_monitor_id_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('artifact_monitor_id_seq', 1, false);


--
-- Data for Name: artifact_query; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY artifact_query (artifact_query_id, group_artifact_id, user_id, query_name, query_type) FROM stdin;
\.


--
-- Name: artifact_query_artifact_query_id_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('artifact_query_artifact_query_id_seq', 1, false);


--
-- Data for Name: artifact_query_fields; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY artifact_query_fields (artifact_query_id, query_field_type, query_field_id, query_field_values) FROM stdin;
\.


--
-- Data for Name: artifact_status; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY artifact_status (id, status_name) FROM stdin;
1	Open
2	Closed
\.


--
-- Name: artifact_status_id_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('artifact_status_id_seq', 3, true);


--
-- Data for Name: artifact_type_monitor; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY artifact_type_monitor (group_artifact_id, user_id) FROM stdin;
\.


--
-- Data for Name: artifact_workflow_event; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY artifact_workflow_event (event_id, group_artifact_id, field_id, from_value_id, to_value_id) FROM stdin;
\.


--
-- Name: artifact_workflow_event_id_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('artifact_workflow_event_id_seq', 1, false);


--
-- Data for Name: artifact_workflow_notify; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY artifact_workflow_notify (event_id, role_id) FROM stdin;
\.


--
-- Data for Name: artifact_workflow_roles; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY artifact_workflow_roles (event_id, role_id) FROM stdin;
\.


--
-- Data for Name: canned_responses; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY canned_responses (response_id, response_title, response_text) FROM stdin;
\.


--
-- Name: canned_responses_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('canned_responses_pk_seq', 1, false);


--
-- Data for Name: country_code; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY country_code (country_name, ccode) FROM stdin;
AFGHANISTAN	AF
ALBANIA	AL
ALGERIA	DZ
AMERICAN SAMOA	AS
ANDORRA	AD
ANGOLA	AO
ANGUILLA	AI
ANTARCTICA	AQ
ANTIGUA AND BARBUDA	AG
ARGENTINA	AR
ARMENIA	AM
ARUBA	AW
AUSTRALIA	AU
AUSTRIA	AT
AZERBAIJAN	AZ
BAHAMAS	BS
BAHRAIN	BH
BANGLADESH	BD
BARBADOS	BB
BELARUS	BY
BELGIUM	BE
BELIZE	BZ
BENIN	BJ
BERMUDA	BM
BHUTAN	BT
BOLIVIA	BO
BOSNIA AND HERZEGOVINA	BA
BOTSWANA	BW
BOUVET ISLAND	BV
BRAZIL	BR
BRITISH INDIAN OCEAN TERRITORY	IO
BRUNEI DARUSSALAM	BN
BULGARIA	BG
BURKINA FASO	BF
BURUNDI	BI
CAMBODIA	KH
CAMEROON	CM
CANADA	CA
CAPE VERDE	CV
CAYMAN ISLANDS	KY
CENTRAL AFRICAN REPUBLIC	CF
CHAD	TD
CHILE	CL
CHINA	CN
CHRISTMAS ISLAND	CX
COCOS (KEELING) ISLANDS	CC
COLOMBIA	CO
COMOROS	KM
CONGO	CG
CONGO, THE DEMOCRATIC REPUBLIC OF THE	CD
COOK ISLANDS	CK
COSTA RICA	CR
COTE D'IVOIRE	CI
CROATIA	HR
CUBA	CU
CYPRUS	CY
CZECH REPUBLIC	CZ
DENMARK	DK
DJIBOUTI	DJ
DOMINICA	DM
DOMINICAN REPUBLIC	DO
EAST TIMOR	TP
ECUADOR	EC
EGYPT	EG
EL SALVADOR	SV
EQUATORIAL GUINEA	GQ
ERITREA	ER
ESTONIA	EE
ETHIOPIA	ET
FALKLAND ISLANDS (MALVINAS)	FK
FAROE ISLANDS	FO
FIJI	FJ
FINLAND	FI
FRANCE	FR
FRENCH GUIANA	GF
FRENCH POLYNESIA	PF
FRENCH SOUTHERN TERRITORIES	TF
GABON	GA
GAMBIA	GM
GEORGIA	GE
GERMANY	DE
GHANA	GH
GIBRALTAR	GI
GREECE	GR
GREENLAND	GL
GRENADA	GD
GUADELOUPE	GP
GUAM	GU
GUATEMALA	GT
GUINEA	GN
GUINEA-BISSAU	GW
GUYANA	GY
HAITI	HT
HEARD ISLAND AND MCDONALD ISLANDS	HM
HOLY SEE (VATICAN CITY STATE)	VA
HONDURAS	HN
HONG KONG	HK
HUNGARY	HU
ICELAND	IS
INDIA	IN
INDONESIA	ID
IRAN, ISLAMIC REPUBLIC OF	IR
IRAQ	IQ
IRELAND	IE
ISRAEL	IL
ITALY	IT
JAMAICA	JM
JAPAN	JP
JORDAN	JO
KAZAKSTAN	KZ
KENYA	KE
KIRIBATI	KI
KOREA, DEMOCRATIC PEOPLE'S REPUBLIC OF	KP
KOREA, REPUBLIC OF	KR
KUWAIT	KW
KYRGYZSTAN	KG
LAO PEOPLE'S DEMOCRATIC REPUBLIC	LA
LATVIA	LV
LEBANON	LB
LESOTHO	LS
LIBERIA	LR
LIBYAN ARAB JAMAHIRIYA	LY
LIECHTENSTEIN	LI
LITHUANIA	LT
LUXEMBOURG	LU
MACAU	MO
MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF	MK
MADAGASCAR	MG
MALAWI	MW
MALAYSIA	MY
MALDIVES	MV
MALI	ML
MALTA	MT
MARSHALL ISLANDS	MH
MARTINIQUE	MQ
MAURITANIA	MR
MAURITIUS	MU
MAYOTTE	YT
MEXICO	MX
MICRONESIA, FEDERATED STATES OF	FM
MOLDOVA, REPUBLIC OF	MD
MONACO	MC
MONGOLIA	MN
MONTSERRAT	MS
MOROCCO	MA
MOZAMBIQUE	MZ
MYANMAR	MM
NAMIBIA	NA
NAURU	NR
NEPAL	NP
NETHERLANDS	NL
NETHERLANDS ANTILLES	AN
NEW CALEDONIA	NC
NEW ZEALAND	NZ
NICARAGUA	NI
NIGER	NE
NIGERIA	NG
NIUE	NU
NORFOLK ISLAND	NF
NORTHERN MARIANA ISLANDS	MP
NORWAY	NO
OMAN	OM
PAKISTAN	PK
PALAU	PW
PALESTINIAN TERRITORY, OCCUPIED	PS
PANAMA	PA
PAPUA NEW GUINEA	PG
PARAGUAY	PY
PERU	PE
PHILIPPINES	PH
PITCAIRN	PN
POLAND	PL
PORTUGAL	PT
PUERTO RICO	PR
QATAR	QA
REUNION	RE
ROMANIA	RO
RUSSIAN FEDERATION	RU
RWANDA	RW
SAINT HELENA	SH
SAINT KITTS AND NEVIS	KN
SAINT LUCIA	LC
SAINT PIERRE AND MIQUELON	PM
SAINT VINCENT AND THE GRENADINES	VC
SAMOA	WS
SAN MARINO	SM
SAO TOME AND PRINCIPE	ST
SAUDI ARABIA	SA
SENEGAL	SN
SEYCHELLES	SC
SIERRA LEONE	SL
SINGAPORE	SG
SLOVAKIA	SK
SLOVENIA	SI
SOLOMON ISLANDS	SB
SOMALIA	SO
SOUTH AFRICA	ZA
SOUTH GEORGIA AND THE SOUTH SANDWICH ISLANDS	GS
SPAIN	ES
SRI LANKA	LK
SUDAN	SD
SURINAME	SR
SVALBARD AND JAN MAYEN	SJ
SWAZILAND	SZ
SWEDEN	SE
SWITZERLAND	CH
SYRIAN ARAB REPUBLIC	SY
TAIWAN, PROVINCE OF CHINA	TW
TAJIKISTAN	TJ
TANZANIA, UNITED REPUBLIC OF	TZ
THAILAND	TH
TOGO	TG
TOKELAU	TK
TONGA	TO
TRINIDAD AND TOBAGO	TT
TUNISIA	TN
TURKEY	TR
TURKMENISTAN	TM
TURKS AND CAICOS ISLANDS	TC
TUVALU	TV
UGANDA	UG
UKRAINE	UA
UNITED ARAB EMIRATES	AE
UNITED STATES	US
UNITED STATES MINOR OUTLYING ISLANDS	UM
URUGUAY	UY
UZBEKISTAN	UZ
VANUATU	VU
VENEZUELA	VE
VIET NAM	VN
VIRGIN ISLANDS, BRITISH	VG
VIRGIN ISLANDS, U.S.	VI
WALLIS AND FUTUNA	WF
WESTERN SAHARA	EH
YEMEN	YE
YUGOSLAVIA	YU
ZAMBIA	ZM
ZIMBABWE	ZW
UNITED KINGDOM	UK
\.


--
-- Data for Name: cron_history; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY cron_history (rundate, job, output) FROM stdin;
\.


--
-- Data for Name: database_changes; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY database_changes (filename) FROM stdin;
\.


--
-- Data for Name: database_startpoint; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY database_startpoint (db_version, db_start_date) FROM stdin;
4.8.50	20090507
\.


--
-- Data for Name: db_images; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY db_images (id, group_id, description, bin_data, filename, filesize, filetype, width, height, upload_date, version) FROM stdin;
\.


--
-- Name: db_images_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('db_images_pk_seq', 1, false);


--
-- Data for Name: deleted_groups; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY deleted_groups (unix_group_name, delete_date, isdeleted) FROM stdin;
\.


--
-- Data for Name: deleted_mailing_lists; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY deleted_mailing_lists (mailing_list_name, delete_date, isdeleted) FROM stdin;
\.


--
-- Data for Name: doc_data; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY doc_data (docid, stateid, title, data, updatedate, createdate, created_by, doc_group, description, language_id, filename, filetype, group_id, filesize, data_words) FROM stdin;
\.


--
-- Name: doc_data_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('doc_data_pk_seq', 1, false);


--
-- Data for Name: doc_groups; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY doc_groups (doc_group, groupname, group_id, parent_doc_group) FROM stdin;
\.


--
-- Name: doc_groups_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('doc_groups_pk_seq', 1, false);


--
-- Data for Name: doc_states; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY doc_states (stateid, name) FROM stdin;
1	active
2	deleted
3	pending
4	hidden
5	private
\.


--
-- Name: doc_states_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('doc_states_pk_seq', 1, false);


--
-- Data for Name: filemodule_monitor; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY filemodule_monitor (id, filemodule_id, user_id) FROM stdin;
\.


--
-- Name: filemodule_monitor_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('filemodule_monitor_pk_seq', 1, false);


--
-- Data for Name: form_keys; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY form_keys (key_id, key, creation_date, is_used) FROM stdin;
\.


--
-- Name: form_keys_key_id_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('form_keys_key_id_seq', 1, false);


--
-- Data for Name: forum; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY forum (msg_id, group_forum_id, posted_by, subject, body, post_date, is_followup_to, thread_id, has_followups, most_recent_date) FROM stdin;
\.


--
-- Data for Name: forum_agg_msg_count; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY forum_agg_msg_count (group_forum_id, count) FROM stdin;
\.


--
-- Data for Name: forum_attachment; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY forum_attachment (attachmentid, userid, dateline, filename, filedata, visible, counter, filesize, msg_id, filehash, mimetype) FROM stdin;
\.


--
-- Name: forum_attachment_attachmentid_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('forum_attachment_attachmentid_seq', 1, false);


--
-- Data for Name: forum_attachment_type; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY forum_attachment_type (extension, mimetype, size, width, height, enabled) FROM stdin;
gif	a:1:{i:0;s:23:"Content-type: image/gif";}	20000	620	280	1
jpeg	a:1:{i:0;s:24:"Content-type: image/jpeg";}	20000	620	280	1
jpg	a:1:{i:0;s:24:"Content-type: image/jpeg";}	100000	0	0	1
jpe	a:1:{i:0;s:24:"Content-type: image/jpeg";}	20000	620	280	1
png	a:1:{i:0;s:23:"Content-type: image/png";}	20000	620	280	1
doc	a:2:{i:0;s:20:"Accept-ranges: bytes";i:1;s:32:"Content-type: application/msword";}	20000	0	0	1
pdf	a:1:{i:0;s:29:"Content-type: application/pdf";}	20000	0	0	1
bmp	a:1:{i:0;s:26:"Content-type: image/bitmap";}	20000	620	280	1
psd	a:1:{i:0;s:29:"Content-type: unknown/unknown";}	20000	0	0	1
zip	a:1:{i:0;s:29:"Content-type: application/zip";}	100000	0	0	1
txt	a:1:{i:0;s:24:"Content-type: plain/text";}	20000	0	0	1
\.


--
-- Data for Name: forum_group_list; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY forum_group_list (group_forum_id, group_id, forum_name, is_public, description, allow_anonymous, send_all_posts_to, moderation_level) FROM stdin;
\.


--
-- Name: forum_group_list_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('forum_group_list_pk_seq', 1, false);


--
-- Data for Name: forum_monitored_forums; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY forum_monitored_forums (monitor_id, forum_id, user_id) FROM stdin;
\.


--
-- Name: forum_monitored_forums_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('forum_monitored_forums_pk_seq', 1, false);


--
-- Data for Name: forum_pending_attachment; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY forum_pending_attachment (attachmentid, userid, dateline, filename, filedata, visible, counter, filesize, msg_id, filehash, mimetype) FROM stdin;
\.


--
-- Name: forum_pending_attachment_attachmentid_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('forum_pending_attachment_attachmentid_seq', 1, false);


--
-- Data for Name: forum_pending_messages; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY forum_pending_messages (msg_id, group_forum_id, posted_by, subject, body, post_date, is_followup_to, thread_id, has_followups, most_recent_date) FROM stdin;
\.


--
-- Name: forum_pending_messages_msg_id_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('forum_pending_messages_msg_id_seq', 1, false);


--
-- Name: forum_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('forum_pk_seq', 1, false);


--
-- Data for Name: forum_saved_place; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY forum_saved_place (saved_place_id, user_id, forum_id, save_date) FROM stdin;
\.


--
-- Name: forum_saved_place_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('forum_saved_place_pk_seq', 1, false);


--
-- Name: forum_thread_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('forum_thread_seq', 1, false);


--
-- Name: foundry_news_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('foundry_news_pk_seq', 1, false);


--
-- Data for Name: frs_dlstats_file; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY frs_dlstats_file (ip_address, file_id, month, day, user_id) FROM stdin;
\.


--
-- Data for Name: frs_dlstats_filetotal_agg; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY frs_dlstats_filetotal_agg (file_id, downloads) FROM stdin;
\.


--
-- Data for Name: frs_file; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY frs_file (file_id, filename, release_id, type_id, processor_id, release_time, file_size, post_date) FROM stdin;
\.


--
-- Name: frs_file_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('frs_file_pk_seq', 1, false);


--
-- Data for Name: frs_filetype; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY frs_filetype (type_id, name) FROM stdin;
1000	.deb
2000	.rpm
3000	.zip
3100	.bz2
3110	.gz
5000	Source .zip
5010	Source .bz2
5020	Source .gz
5100	Source .rpm
5900	Other Source File
8000	.jpg
8100	text
8200	html
8300	pdf
9999	Other
\.


--
-- Name: frs_filetype_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('frs_filetype_pk_seq', 9999, true);


--
-- Data for Name: frs_package; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY frs_package (package_id, group_id, name, status_id, is_public) FROM stdin;
\.


--
-- Name: frs_package_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('frs_package_pk_seq', 1, false);


--
-- Data for Name: frs_processor; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY frs_processor (processor_id, name) FROM stdin;
1000	i386
6000	IA64
7000	Alpha
8000	Any
2000	PPC
3000	MIPS
4000	Sparc
5000	UltraSparc
9999	Other
\.


--
-- Name: frs_processor_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('frs_processor_pk_seq', 9999, true);


--
-- Data for Name: frs_release; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY frs_release (release_id, package_id, name, notes, changes, status_id, preformatted, release_date, released_by) FROM stdin;
\.


--
-- Name: frs_release_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('frs_release_pk_seq', 1, false);


--
-- Data for Name: frs_status; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY frs_status (status_id, name) FROM stdin;
1	Active
3	Hidden
\.


--
-- Name: frs_status_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('frs_status_pk_seq', 3, true);


--
-- Data for Name: group_activity_monitor; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY group_activity_monitor (group_id, user_id, filter) FROM stdin;
\.


--
-- Data for Name: group_cvs_history; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY group_cvs_history (id, group_id, user_name, cvs_commits, cvs_commits_wk, cvs_adds, cvs_adds_wk) FROM stdin;
\.


--
-- Name: group_cvs_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('group_cvs_history_id_seq', 1, false);


--
-- Data for Name: group_history; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY group_history (group_history_id, group_id, field_name, old_value, mod_by, adddate) FROM stdin;
\.


--
-- Name: group_history_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('group_history_pk_seq', 1, false);


--
-- Data for Name: group_join_request; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY group_join_request (group_id, user_id, comments, request_date) FROM stdin;
\.


--
-- Data for Name: group_plugin; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY group_plugin (group_plugin_id, group_id, plugin_id) FROM stdin;
1	2	1
2	3	1
3	4	1
4	1	1
\.


--
-- Name: group_plugin_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('group_plugin_pk_seq', 4, true);


--
-- Data for Name: groups; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY groups (group_id, group_name, homepage, is_public, status, unix_group_name, unix_box, http_domain, short_description, register_purpose, license_other, register_time, rand_hash, use_mail, use_survey, use_forum, use_pm, use_scm, use_news, type_id, use_docman, new_doc_address, send_all_docs, use_pm_depend_box, use_ftp, use_tracker, use_frs, use_stats, enable_pserver, enable_anonscm, license, scm_box) FROM stdin;
2	Stats Group	\N	0	A	stats	shell1	\N	\N	\N	\N	0	\N	1	1	1	1	1	1	1	1		0	1	1	1	1	1	1	1	100	cvs1
3	News Group	\N	0	A	newsadmin	shell1	\N	\N	\N	\N	0	\N	1	1	1	1	1	1	1	1		0	1	1	1	1	1	1	1	100	cvs1
4	Peer Ratings Group	\N	0	A	peerrating	shell1	\N	\N	\N	\N	0	\N	1	1	1	1	1	1	1	1		0	1	1	1	1	1	1	1	100	cvs1
1	Master Group	\N	0	A	siteadmin	shell1	\N	\N	\N	\N	0	\N	1	1	1	1	1	1	1	1		0	1	1	1	1	1	1	1	100	cvs1
5	Template Project	\N	1	P	template	shell1	\N	Project to house templates used to build other projects	\N	\N	1120266772	\N	1	1	1	1	1	1	1	1		0	1	1	1	1	1	1	1	100	\N
\.


--
-- Name: groups_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('groups_pk_seq', 5, true);


--
-- Data for Name: licenses; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY licenses (license_id, license_name) FROM stdin;
100	None
101	GNU General Public License (GPL)
102	GNU Library Public License (LGPL)
103	BSD License
104	MIT License
105	Artistic License
106	Mozilla Public License 1.0 (MPL)
107	Qt Public License (QPL)
108	IBM Public License
109	MITRE Collaborative Virtual Workspace License (CVW License)
110	Ricoh Source Code Public License
111	Python License
112	zlib/libpng License
113	Apache Software License
114	Vovida Software License 1.0
115	Sun Internet Standards Source License (SISSL)
116	Intel Open Source License
117	Mozilla Public License 1.1 (MPL 1.1)
118	Jabber Open Source License
119	Nokia Open Source License
120	Sleepycat License
121	Nethack General Public License
122	IBM Common Public License
123	Apple Public Source License
124	Public Domain
125	Website Only
126	Other/Proprietary License
\.


--
-- Name: licenses_license_id_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('licenses_license_id_seq', 126, true);


--
-- Data for Name: mail_group_list; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY mail_group_list (group_list_id, group_id, list_name, is_public, password, list_admin, status, description) FROM stdin;
\.


--
-- Name: mail_group_list_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('mail_group_list_pk_seq', 1, false);


--
-- Data for Name: massmail_queue; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY massmail_queue (id, type, subject, message, queued_date, last_userid, failed_date, finished_date) FROM stdin;
\.


--
-- Name: massmail_queue_id_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('massmail_queue_id_seq', 1, false);


--
-- Data for Name: news_bytes; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY news_bytes (id, group_id, submitted_by, is_approved, post_date, forum_id, summary, details) FROM stdin;
\.


--
-- Name: news_bytes_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('news_bytes_pk_seq', 1, false);


--
-- Data for Name: nss_groups; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY nss_groups (user_id, group_id, name, gid) FROM stdin;
0	2	stats	10002
0	3	newsadmin	10003
0	4	peerrating	10004
0	1	siteadmin	10001
0	2	scm_stats	50002
0	3	scm_newsadmin	50003
0	4	scm_peerrating	50004
0	1	scm_siteadmin	50001
\.


--
-- Data for Name: nss_usergroups; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY nss_usergroups (uid, gid, user_id, group_id, user_name, unix_group_name) FROM stdin;
\.


--
-- Data for Name: people_job; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY people_job (job_id, group_id, created_by, title, description, post_date, status_id, category_id) FROM stdin;
\.


--
-- Data for Name: people_job_category; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY people_job_category (category_id, name, private_flag) FROM stdin;
1	Developer	0
2	Project Manager	0
3	Unix Admin	0
4	Doc Writer	0
5	Tester	0
6	Support Manager	0
7	Graphic/Other Designer	0
\.


--
-- Name: people_job_category_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('people_job_category_pk_seq', 7, true);


--
-- Data for Name: people_job_inventory; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY people_job_inventory (job_inventory_id, job_id, skill_id, skill_level_id, skill_year_id) FROM stdin;
\.


--
-- Name: people_job_inventory_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('people_job_inventory_pk_seq', 1, false);


--
-- Name: people_job_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('people_job_pk_seq', 1, false);


--
-- Data for Name: people_job_status; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY people_job_status (status_id, name) FROM stdin;
1	Open
2	Filled
3	Deleted
\.


--
-- Name: people_job_status_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('people_job_status_pk_seq', 1, false);


--
-- Data for Name: people_skill; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY people_skill (skill_id, name) FROM stdin;
\.


--
-- Data for Name: people_skill_inventory; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY people_skill_inventory (skill_inventory_id, user_id, skill_id, skill_level_id, skill_year_id) FROM stdin;
\.


--
-- Name: people_skill_inventory_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('people_skill_inventory_pk_seq', 1, false);


--
-- Data for Name: people_skill_level; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY people_skill_level (skill_level_id, name) FROM stdin;
1	Want to Learn
2	Competent
3	Wizard
4	Wrote The Book
5	Wrote It
\.


--
-- Name: people_skill_level_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('people_skill_level_pk_seq', 5, true);


--
-- Name: people_skill_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('people_skill_pk_seq', 1, false);


--
-- Data for Name: people_skill_year; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY people_skill_year (skill_year_id, name) FROM stdin;
1	< 6 Months
2	6 Mo - 2 yr
3	2 yr - 5 yr
4	5 yr - 10 yr
5	> 10 years
\.


--
-- Name: people_skill_year_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('people_skill_year_pk_seq', 5, true);


--
-- Name: plugin_cvstracker_artifact_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('plugin_cvstracker_artifact_seq', 1, false);


--
-- Data for Name: plugin_cvstracker_data_artifact; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY plugin_cvstracker_data_artifact (id, kind, group_artifact_id, project_task_id) FROM stdin;
\.


--
-- Data for Name: plugin_cvstracker_data_master; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY plugin_cvstracker_data_master (id, holder_id, log_text, file, prev_version, actual_version, author, cvs_date) FROM stdin;
\.


--
-- Name: plugin_cvstracker_master_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('plugin_cvstracker_master_seq', 1, false);


--
-- Data for Name: plugins; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY plugins (plugin_id, plugin_name, plugin_desc) FROM stdin;
1	scmcvs	CVS Plugin
2	scmsvn	SVN Plugin
3	cvstracker	CVS Tracker Integration
4	online_help	Online Help Plugin
\.


--
-- Name: plugins_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('plugins_pk_seq', 4, true);


--
-- Data for Name: prdb_dbs; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY prdb_dbs (dbid, group_id, dbname, dbusername, dbuserpass, requestdate, dbtype, created_by, state) FROM stdin;
\.


--
-- Name: prdb_dbs_dbid_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('prdb_dbs_dbid_seq', 1, false);


--
-- Data for Name: prdb_states; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY prdb_states (stateid, statename) FROM stdin;
\.


--
-- Data for Name: prdb_types; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY prdb_types (dbtypeid, dbservername, dbsoftware) FROM stdin;
\.


--
-- Data for Name: project_assigned_to; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY project_assigned_to (project_assigned_id, project_task_id, assigned_to_id) FROM stdin;
\.


--
-- Name: project_assigned_to_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('project_assigned_to_pk_seq', 1, false);


--
-- Name: project_categor_category_id_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('project_categor_category_id_seq', 100, true);


--
-- Data for Name: project_category; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY project_category (category_id, group_project_id, category_name) FROM stdin;
100	1	None
\.


--
-- Data for Name: project_counts_agg; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY project_counts_agg (group_project_id, count, open_count) FROM stdin;
1	1	1
\.


--
-- Data for Name: project_dependencies; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY project_dependencies (project_depend_id, project_task_id, is_dependent_on_task_id, link_type) FROM stdin;
\.


--
-- Name: project_dependencies_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('project_dependencies_pk_seq', 1, false);


--
-- Data for Name: project_group_list; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY project_group_list (group_project_id, group_id, project_name, is_public, description, send_all_posts_to) FROM stdin;
1	1	Default	0	Default Project - Don't Change	\N
\.


--
-- Name: project_group_list_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('project_group_list_pk_seq', 1, true);


--
-- Data for Name: project_history; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY project_history (project_history_id, project_task_id, field_name, old_value, mod_by, mod_date) FROM stdin;
\.


--
-- Name: project_history_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('project_history_pk_seq', 1, false);


--
-- Name: project_messa_project_messa_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('project_messa_project_messa_seq', 1, false);


--
-- Data for Name: project_messages; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY project_messages (project_message_id, project_task_id, body, posted_by, postdate) FROM stdin;
\.


--
-- Data for Name: project_metric; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY project_metric (ranking, percentile, group_id) FROM stdin;
\.


--
-- Name: project_metric_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('project_metric_pk_seq', 1, false);


--
-- Data for Name: project_metric_tmp1; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY project_metric_tmp1 (ranking, group_id, value) FROM stdin;
\.


--
-- Name: project_metric_tmp1_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('project_metric_tmp1_pk_seq', 1, false);


--
-- Name: project_metric_wee_ranking1_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('project_metric_wee_ranking1_seq', 1, false);


--
-- Data for Name: project_status; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY project_status (status_id, status_name) FROM stdin;
1	Open
2	Closed
\.


--
-- Name: project_status_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('project_status_pk_seq', 1, false);


--
-- Data for Name: project_sums_agg; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY project_sums_agg (group_id, type, count) FROM stdin;
\.


--
-- Data for Name: project_tags; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY project_tags (group_id, name) FROM stdin;
\.


--
-- Data for Name: project_task; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY project_task (project_task_id, group_project_id, summary, details, percent_complete, priority, hours, start_date, end_date, created_by, status_id, category_id, duration, parent_id, last_modified_date) FROM stdin;
1	1			0	0	0	0	0	100	1	100	0	0	1108701981
\.


--
-- Data for Name: project_task_artifact; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY project_task_artifact (project_task_id, artifact_id) FROM stdin;
\.


--
-- Data for Name: project_task_external_order; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY project_task_external_order (project_task_id, external_id) FROM stdin;
\.


--
-- Name: project_task_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('project_task_pk_seq', 1, true);


--
-- Data for Name: project_weekly_metric; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY project_weekly_metric (ranking, percentile, group_id) FROM stdin;
\.


--
-- Name: project_weekly_metric_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('project_weekly_metric_pk_seq', 1, false);


--
-- Data for Name: prweb_vhost; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY prweb_vhost (vhostid, vhost_name, docdir, cgidir, group_id) FROM stdin;
\.


--
-- Name: prweb_vhost_vhostid_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('prweb_vhost_vhostid_seq', 1, false);


--
-- Data for Name: rep_group_act_daily; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY rep_group_act_daily (group_id, day, tracker_opened, tracker_closed, forum, docs, downloads, cvs_commits, tasks_opened, tasks_closed) FROM stdin;
\.


--
-- Data for Name: rep_group_act_monthly; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY rep_group_act_monthly (group_id, month, tracker_opened, tracker_closed, forum, docs, downloads, cvs_commits, tasks_opened, tasks_closed) FROM stdin;
\.


--
-- Data for Name: rep_group_act_weekly; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY rep_group_act_weekly (group_id, week, tracker_opened, tracker_closed, forum, docs, downloads, cvs_commits, tasks_opened, tasks_closed) FROM stdin;
\.


--
-- Data for Name: rep_groups_added_daily; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY rep_groups_added_daily (day, added) FROM stdin;
\.


--
-- Data for Name: rep_groups_added_monthly; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY rep_groups_added_monthly (month, added) FROM stdin;
\.


--
-- Data for Name: rep_groups_added_weekly; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY rep_groups_added_weekly (week, added) FROM stdin;
\.


--
-- Data for Name: rep_groups_cum_daily; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY rep_groups_cum_daily (day, total) FROM stdin;
\.


--
-- Data for Name: rep_groups_cum_monthly; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY rep_groups_cum_monthly (month, total) FROM stdin;
\.


--
-- Data for Name: rep_groups_cum_weekly; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY rep_groups_cum_weekly (week, total) FROM stdin;
\.


--
-- Data for Name: rep_time_category; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY rep_time_category (time_code, category_name) FROM stdin;
1	Coding
2	Testing
3	Meeting
\.


--
-- Name: rep_time_category_time_code_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('rep_time_category_time_code_seq', 3, true);


--
-- Data for Name: rep_time_tracking; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY rep_time_tracking (week, report_date, user_id, project_task_id, time_code, hours) FROM stdin;
\.


--
-- Data for Name: rep_user_act_daily; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY rep_user_act_daily (user_id, day, tracker_opened, tracker_closed, forum, docs, cvs_commits, tasks_opened, tasks_closed) FROM stdin;
\.


--
-- Data for Name: rep_user_act_monthly; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY rep_user_act_monthly (user_id, month, tracker_opened, tracker_closed, forum, docs, cvs_commits, tasks_opened, tasks_closed) FROM stdin;
\.


--
-- Data for Name: rep_user_act_weekly; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY rep_user_act_weekly (user_id, week, tracker_opened, tracker_closed, forum, docs, cvs_commits, tasks_opened, tasks_closed) FROM stdin;
\.


--
-- Data for Name: rep_users_added_daily; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY rep_users_added_daily (day, added) FROM stdin;
\.


--
-- Data for Name: rep_users_added_monthly; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY rep_users_added_monthly (month, added) FROM stdin;
\.


--
-- Data for Name: rep_users_added_weekly; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY rep_users_added_weekly (week, added) FROM stdin;
\.


--
-- Data for Name: rep_users_cum_daily; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY rep_users_cum_daily (day, total) FROM stdin;
\.


--
-- Data for Name: rep_users_cum_monthly; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY rep_users_cum_monthly (month, total) FROM stdin;
\.


--
-- Data for Name: rep_users_cum_weekly; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY rep_users_cum_weekly (week, total) FROM stdin;
\.


--
-- Data for Name: role; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY role (role_id, group_id, role_name) FROM stdin;
1	1	Default
2	2	Admin
3	2	Senior Developer
4	2	Junior Developer
5	2	Doc Writer
6	2	Support Tech
7	3	Admin
8	3	Senior Developer
9	3	Junior Developer
10	3	Doc Writer
11	3	Support Tech
12	4	Admin
13	4	Senior Developer
14	4	Junior Developer
15	4	Doc Writer
16	4	Support Tech
17	1	Admin
18	1	Senior Developer
19	1	Junior Developer
20	1	Doc Writer
21	1	Support Tech
\.


--
-- Name: role_role_id_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('role_role_id_seq', 21, true);


--
-- Data for Name: role_setting; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY role_setting (role_id, section_name, ref_id, value) FROM stdin;
2	projectadmin	0	A
2	frs	0	1
2	scm	0	1
2	docman	0	1
2	forumadmin	0	2
2	trackeradmin	0	2
2	pmadmin	0	2
3	projectadmin	0	0
3	frs	0	1
3	scm	0	1
3	docman	0	1
3	forumadmin	0	2
3	trackeradmin	0	2
3	pmadmin	0	2
4	projectadmin	0	0
4	frs	0	0
4	scm	0	1
4	docman	0	0
4	forumadmin	0	0
4	trackeradmin	0	0
4	pmadmin	0	0
5	projectadmin	0	0
5	frs	0	0
5	scm	0	0
5	docman	0	1
5	forumadmin	0	0
5	trackeradmin	0	0
5	pmadmin	0	0
6	projectadmin	0	0
6	frs	0	0
6	scm	0	0
6	docman	0	1
6	forumadmin	0	0
6	trackeradmin	0	0
6	pmadmin	0	0
7	projectadmin	0	A
7	frs	0	1
7	scm	0	1
7	docman	0	1
7	forumadmin	0	2
7	trackeradmin	0	2
7	pmadmin	0	2
8	projectadmin	0	0
8	frs	0	1
8	scm	0	1
8	docman	0	1
8	forumadmin	0	2
8	trackeradmin	0	2
8	pmadmin	0	2
9	projectadmin	0	0
9	frs	0	0
9	scm	0	1
9	docman	0	0
9	forumadmin	0	0
9	trackeradmin	0	0
9	pmadmin	0	0
10	projectadmin	0	0
10	frs	0	0
10	scm	0	0
10	docman	0	1
10	forumadmin	0	0
10	trackeradmin	0	0
10	pmadmin	0	0
11	projectadmin	0	0
11	frs	0	0
11	scm	0	0
11	docman	0	1
11	forumadmin	0	0
11	trackeradmin	0	0
11	pmadmin	0	0
12	projectadmin	0	A
12	frs	0	1
12	scm	0	1
12	docman	0	1
12	forumadmin	0	2
12	trackeradmin	0	2
12	pmadmin	0	2
13	projectadmin	0	0
13	frs	0	1
13	scm	0	1
13	docman	0	1
13	forumadmin	0	2
13	trackeradmin	0	2
13	pmadmin	0	2
14	projectadmin	0	0
14	frs	0	0
14	scm	0	1
14	docman	0	0
14	forumadmin	0	0
14	trackeradmin	0	0
14	pmadmin	0	0
15	projectadmin	0	0
15	frs	0	0
15	scm	0	0
15	docman	0	1
15	forumadmin	0	0
15	trackeradmin	0	0
15	pmadmin	0	0
16	projectadmin	0	0
16	frs	0	0
16	scm	0	0
16	docman	0	1
16	forumadmin	0	0
16	trackeradmin	0	0
16	pmadmin	0	0
17	projectadmin	0	A
17	frs	0	1
17	scm	0	1
17	docman	0	1
17	forumadmin	0	2
17	trackeradmin	0	2
17	tracker	100	3
17	pmadmin	0	2
17	pm	1	3
18	projectadmin	0	0
18	frs	0	1
18	scm	0	1
18	docman	0	1
18	forumadmin	0	2
18	trackeradmin	0	2
18	tracker	100	2
18	pmadmin	0	2
18	pm	1	2
19	projectadmin	0	0
19	frs	0	0
19	scm	0	1
19	docman	0	0
19	forumadmin	0	0
19	trackeradmin	0	0
19	tracker	100	1
19	pmadmin	0	0
19	pm	1	1
20	projectadmin	0	0
20	frs	0	0
20	scm	0	0
20	docman	0	1
20	forumadmin	0	0
20	trackeradmin	0	0
20	tracker	100	0
20	pmadmin	0	0
20	pm	1	0
21	projectadmin	0	0
21	frs	0	0
21	scm	0	0
21	docman	0	1
21	forumadmin	0	0
21	trackeradmin	0	0
21	tracker	100	2
21	pmadmin	0	0
21	pm	1	0
\.


--
-- Data for Name: skills_data; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY skills_data (skills_data_id, user_id, type, title, start, finish, keywords) FROM stdin;
\.


--
-- Name: skills_data_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('skills_data_pk_seq', 1, false);


--
-- Data for Name: skills_data_types; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY skills_data_types (type_id, type_name) FROM stdin;
0	Unspecified
1	Project
2	Training
3	Proposal
4	Investigation
\.


--
-- Name: skills_data_types_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('skills_data_types_pk_seq', 4, true);


--
-- Data for Name: snippet; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY snippet (snippet_id, created_by, name, description, type, language, license, category) FROM stdin;
\.


--
-- Data for Name: snippet_package; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY snippet_package (snippet_package_id, created_by, name, description, category, language) FROM stdin;
\.


--
-- Data for Name: snippet_package_item; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY snippet_package_item (snippet_package_item_id, snippet_package_version_id, snippet_version_id) FROM stdin;
\.


--
-- Name: snippet_package_item_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('snippet_package_item_pk_seq', 1, false);


--
-- Name: snippet_package_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('snippet_package_pk_seq', 1, false);


--
-- Data for Name: snippet_package_version; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY snippet_package_version (snippet_package_version_id, snippet_package_id, changes, version, submitted_by, post_date) FROM stdin;
\.


--
-- Name: snippet_package_version_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('snippet_package_version_pk_seq', 1, false);


--
-- Name: snippet_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('snippet_pk_seq', 1, false);


--
-- Data for Name: snippet_version; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY snippet_version (snippet_version_id, snippet_id, changes, version, submitted_by, post_date, code) FROM stdin;
\.


--
-- Name: snippet_version_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('snippet_version_pk_seq', 1, false);


--
-- Data for Name: stats_agg_logo_by_day; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY stats_agg_logo_by_day (day, count) FROM stdin;
\.


--
-- Data for Name: stats_agg_logo_by_group; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY stats_agg_logo_by_group (month, day, group_id, count) FROM stdin;
\.


--
-- Data for Name: stats_agg_pages_by_day; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY stats_agg_pages_by_day (day, count) FROM stdin;
\.


--
-- Data for Name: stats_agg_site_by_group; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY stats_agg_site_by_group (month, day, group_id, count) FROM stdin;
\.


--
-- Data for Name: stats_cvs_group; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY stats_cvs_group (month, day, group_id, checkouts, commits, adds) FROM stdin;
\.


--
-- Data for Name: stats_cvs_user; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY stats_cvs_user (month, day, group_id, user_id, checkouts, commits, adds) FROM stdin;
\.


--
-- Data for Name: stats_project; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY stats_project (month, day, group_id, file_releases, msg_posted, msg_uniq_auth, bugs_opened, bugs_closed, support_opened, support_closed, patches_opened, patches_closed, artifacts_opened, artifacts_closed, tasks_opened, tasks_closed, help_requests) FROM stdin;
\.


--
-- Data for Name: stats_project_developers; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY stats_project_developers (month, day, group_id, developers) FROM stdin;
\.


--
-- Data for Name: stats_project_metric; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY stats_project_metric (month, day, ranking, percentile, group_id) FROM stdin;
\.


--
-- Data for Name: stats_project_months; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY stats_project_months (month, group_id, developers, group_ranking, group_metric, logo_showings, downloads, site_views, subdomain_views, page_views, file_releases, msg_posted, msg_uniq_auth, bugs_opened, bugs_closed, support_opened, support_closed, patches_opened, patches_closed, artifacts_opened, artifacts_closed, tasks_opened, tasks_closed, help_requests, cvs_checkouts, cvs_commits, cvs_adds) FROM stdin;
\.


--
-- Data for Name: stats_site; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY stats_site (month, day, uniq_users, sessions, total_users, new_users, new_projects) FROM stdin;
\.


--
-- Data for Name: stats_site_months; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY stats_site_months (month, site_page_views, downloads, subdomain_views, msg_posted, bugs_opened, bugs_closed, support_opened, support_closed, patches_opened, patches_closed, artifacts_opened, artifacts_closed, tasks_opened, tasks_closed, help_requests, cvs_checkouts, cvs_commits, cvs_adds) FROM stdin;
\.


--
-- Data for Name: stats_site_pages_by_day; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY stats_site_pages_by_day (month, day, site_page_views) FROM stdin;
\.


--
-- Data for Name: stats_site_pages_by_month; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY stats_site_pages_by_month (month, site_page_views) FROM stdin;
\.


--
-- Data for Name: stats_subd_pages; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY stats_subd_pages (month, day, group_id, pages) FROM stdin;
\.


--
-- Name: supported_langu_language_id_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('supported_langu_language_id_seq', 24, true);


--
-- Data for Name: supported_languages; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY supported_languages (language_id, name, filename, classname, language_code) FROM stdin;
1	English	English.class	English	en   
2	Japanese	Japanese.class	Japanese	ja   
4	Spanish	Spanish.class	Spanish	es   
5	Thai	Thai.class	Thai	th   
6	German	German.class	German	de   
8	Italian	Italian.class	Italian	it   
10	Swedish	Swedish.class	Swedish	sv   
12	Dutch	Dutch.class	Dutch	nl   
14	Catalan	Catalan.class	Catalan	ca   
22	Korean	Korean.class	Korean	ko   
20	Bulgarian	Bulgarian.class	Bulgarian	bg   
17	Russian	Russian.class	Russian	ru   
7	French	French.class	French	fr   
23	Smpl.Chinese	SimplifiedChinese.class	SimplifiedChinese	zh-cn
11	Trad.Chinese	Chinese.class	Chinese	zh-tw
16	Pt. Brazilian	PortugueseBrazilian.class	PortugueseBrazilian	pt-br
\.


--
-- Data for Name: survey_question_types; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY survey_question_types (id, type) FROM stdin;
1	Radio Buttons 1-5
2	Text Area
3	Radio Buttons Yes/No
4	Comment Only
5	Text Field
100	None
\.


--
-- Name: survey_question_types_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('survey_question_types_pk_seq', 1, false);


--
-- Data for Name: survey_questions; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY survey_questions (question_id, group_id, question, question_type) FROM stdin;
\.


--
-- Name: survey_questions_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('survey_questions_pk_seq', 1, false);


--
-- Data for Name: survey_rating_aggregate; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY survey_rating_aggregate (type, id, response, count) FROM stdin;
\.


--
-- Data for Name: survey_rating_response; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY survey_rating_response (user_id, type, id, response, post_date) FROM stdin;
\.


--
-- Data for Name: survey_responses; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY survey_responses (user_id, group_id, survey_id, question_id, response, post_date) FROM stdin;
\.


--
-- Data for Name: surveys; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY surveys (survey_id, group_id, survey_title, survey_questions, is_active) FROM stdin;
\.


--
-- Name: surveys_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('surveys_pk_seq', 1, false);


--
-- Data for Name: themes; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY themes (theme_id, dirname, fullname, enabled) FROM stdin;
1	gforge	Default Theme	t
2	osx	OSX	t
4	ultralite	Ultra-Lite Text-only Theme	t
\.


--
-- Name: themes_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('themes_pk_seq', 1, true);


--
-- Name: themes_theme_id_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('themes_theme_id_seq', 4, true);


--
-- Data for Name: trove_agg; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY trove_agg (trove_cat_id, group_id, group_name, unix_group_name, status, register_time, short_description, percentile, ranking) FROM stdin;
\.


--
-- Data for Name: trove_cat; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY trove_cat (trove_cat_id, version, parent, root_parent, shortname, fullname, description, count_subcat, count_subproj, fullpath, fullpath_ids) FROM stdin;
1	2000031601	0	0	audience	Intended Audience	The main class of people likely to be interested in this resource.	0	0	Intended Audience	1
2	2000032401	1	1	endusers	End Users/Desktop	Programs and resources for software end users. Software for the desktop.	0	0	Intended Audience :: End Users/Desktop	1 :: 2
3	2000041101	1	1	developers	Developers	Programs and resources for software developers, to include libraries.	0	0	Intended Audience :: Developers	1 :: 3
4	2000031601	1	1	sysadmins	System Administrators	Programs and resources for people who administer computers and networks.	0	0	Intended Audience :: System Administrators	1 :: 4
5	2000040701	1	1	other	Other Audience	Programs and resources for an unlisted audience.	0	0	Intended Audience :: Other Audience	1 :: 5
6	2000031601	0	0	developmentstatus	Development Status	An indication of the development status of the software or resource.	0	0	Development Status	6
7	2000040701	6	6	planning	1 - Planning	This resource is in the planning stages only. There is no code.	0	0	Development Status :: 1 - Planning	6 :: 7
8	2000040701	6	6	prealpha	2 - Pre-Alpha	There is code for this project, but it is not usable except for further development.	0	0	Development Status :: 2 - Pre-Alpha	6 :: 8
9	2000041101	6	6	alpha	3 - Alpha	Resource is in early development, and probably incomplete and/or extremely buggy.	0	0	Development Status :: 3 - Alpha	6 :: 9
10	2000040701	6	6	beta	4 - Beta	Resource is in late phases of development. Deliverables are essentially complete, but may still have significant bugs.	0	0	Development Status :: 4 - Beta	6 :: 10
11	2000040701	6	6	production	5 - Production/Stable	Deliverables are complete and usable by the intended audience.	0	0	Development Status :: 5 - Production/Stable	6 :: 11
12	2000040701	6	6	mature	6 - Mature	This resource has an extensive history of successful use and has probably undergone several stable revisions.	0	0	Development Status :: 6 - Mature	6 :: 12
13	2000031601	0	0	license	License	License terms under which the resource is distributed.	0	0	License	13
197	2000032001	13	13	publicdomain	Public Domain	Public Domain. No author-retained rights.	0	0	License :: Public Domain	13 :: 197
196	2000040701	13	13	other	Other/Proprietary License	Non OSI-Approved/Proprietary license.	0	0	License :: Other/Proprietary License	13 :: 196
14	2000032401	13	13	osi	OSI Approved	Licenses that have been approved by OSI as approved	0	0	License :: OSI Approved	13 :: 14
303	2001041701	14	13	nethack	Nethack General Public License	Nethack General Public License	0	0	License :: OSI Approved :: Nethack General Public License	13 :: 14 :: 303
141	2000032001	136	18	clustering	Clustering/Distributed Networks	Tools for automatically distributing computation across a network.	0	0	Topic :: System :: Clustering/Distributed Networks	18 :: 136 :: 141
139	2000032001	136	18	boot	Boot	Programs for bootstrapping your OS.	0	0	Topic :: System :: Boot	18 :: 136 :: 139
140	2000032001	139	18	init	Init	Init-time programs to start system services after boot.	0	0	Topic :: System :: Boot :: Init	18 :: 136 :: 139 :: 140
138	2000032001	136	18	benchmark	Benchmark	Programs for benchmarking system performance.	0	0	Topic :: System :: Benchmark	18 :: 136 :: 138
74	2000042701	136	18	emulators	Emulators	Emulations of foreign operating systme and machines.	0	0	Topic :: System :: Emulators	18 :: 136 :: 74
19	2000032001	136	18	archiving	Archiving	Tools for maintaining and searching software or document archives.	0	0	Topic :: System :: Archiving	18 :: 136 :: 19
137	2000032001	19	18	backup	Backup	Programs to manage and sequence system backup.	0	0	Topic :: System :: Archiving :: Backup	18 :: 136 :: 19 :: 137
42	2000031601	19	18	compression	Compression	Tools and libraries for data compression.	0	0	Topic :: System :: Archiving :: Compression	18 :: 136 :: 19 :: 42
41	2000031601	19	18	packaging	Packaging	Tools for packing and unpacking multi-file formats. Includes data-only formats and software package systems.	0	0	Topic :: System :: Archiving :: Packaging	18 :: 136 :: 19 :: 41
132	2000032001	18	18	religion	Religion	Programs relating to religion and sacred texts.	0	0	Topic :: Religion	18 :: 132
129	2000031701	18	18	office	Office/Business	Software for assisting and organizing work at your desk.	0	0	Topic :: Office/Business	18 :: 129
131	2000032001	129	18	suites	Office Suites	Integrated office suites (word processing, presentation, spreadsheet, database, etc).	0	0	Topic :: Office/Business :: Office Suites	18 :: 129 :: 131
130	2000031701	129	18	scheduling	Scheduling	Projects for scheduling time, to include project management.	0	0	Topic :: Office/Business :: Scheduling	18 :: 129 :: 130
75	2000031701	129	18	financial	Financial	Programs related to finance.	0	0	Topic :: Office/Business :: Financial	18 :: 129 :: 75
79	2000031601	75	18	pointofsale	Point-Of-Sale	Point-Of-Sale applications.	0	0	Topic :: Office/Business :: Financial :: Point-Of-Sale	18 :: 129 :: 75 :: 79
78	2000031601	75	18	spreadsheet	Spreadsheet	Spreadsheet applications.	0	0	Topic :: Office/Business :: Financial :: Spreadsheet	18 :: 129 :: 75 :: 78
77	2000031601	75	18	investment	Investment	Programs for assisting in financial investment.	0	0	Topic :: Office/Business :: Financial :: Investment	18 :: 129 :: 75 :: 77
76	2000031601	75	18	accounting	Accounting	Checkbook balancers and accounting programs.	0	0	Topic :: Office/Business :: Financial :: Accounting	18 :: 129 :: 75 :: 76
234	2000040701	18	18	other	Other/Nonlisted Topic	Topic does not fit into any listed category.	0	0	Topic :: Other/Nonlisted Topic	18 :: 234
156	2000032001	18	18	terminals	Terminals	Terminal emulators, terminal programs, and terminal session utilities.	0	0	Topic :: Terminals	18 :: 156
159	2000032001	156	18	telnet	Telnet	Support for telnet; terminal sessions across Internet links.	0	0	Topic :: Terminals :: Telnet	18 :: 156 :: 159
158	2000032001	156	18	virtual	Terminal Emulators/X Terminals	Programs to handle multiple terminal sessions. Includes terminal emulations for X and other window systems.	0	0	Topic :: Terminals :: Terminal Emulators/X Terminals	18 :: 156 :: 158
157	2000032001	156	18	serial	Serial	Dialup, terminal emulation, and file transfer over serial lines.	0	0	Topic :: Terminals :: Serial	18 :: 156 :: 157
115	2000031701	113	18	capture	Capture/Recording	Sound capture and recording.	0	0	Topic :: Multimedia :: Sound/Audio :: Capture/Recording	18 :: 99 :: 113 :: 115
114	2000031701	113	18	analysis	Analysis	Sound analysis tools, to include frequency analysis.	0	0	Topic :: Multimedia :: Sound/Audio :: Analysis	18 :: 99 :: 113 :: 114
100	2000031601	99	18	graphics	Graphics	Tools and resources for computer graphics.	0	0	Topic :: Multimedia :: Graphics	18 :: 99 :: 100
112	2000031701	100	18	viewers	Viewers	Programs that can display various graphics formats.	0	0	Topic :: Multimedia :: Graphics :: Viewers	18 :: 99 :: 100 :: 112
111	2000031701	100	18	presentation	Presentation	Tools for generating presentation graphics and slides.	0	0	Topic :: Multimedia :: Graphics :: Presentation	18 :: 99 :: 100 :: 111
110	2000031701	100	18	3drendering	3D Rendering	Programs which render 3D models.	0	0	Topic :: Multimedia :: Graphics :: 3D Rendering	18 :: 99 :: 100 :: 110
109	2000031701	100	18	3dmodeling	3D Modeling	Programs for working with 3D Models.	0	0	Topic :: Multimedia :: Graphics :: 3D Modeling	18 :: 99 :: 100 :: 109
106	2000031701	100	18	editors	Editors	Drawing, painting, and structured editing programs.	0	0	Topic :: Multimedia :: Graphics :: Editors	18 :: 99 :: 100 :: 106
108	2000031701	106	18	raster	Raster-Based	Raster/Bitmap based drawing programs.	0	0	Topic :: Multimedia :: Graphics :: Editors :: Raster-Based	18 :: 99 :: 100 :: 106 :: 108
107	2000031701	106	18	vector	Vector-Based	Vector-Based drawing programs.	0	0	Topic :: Multimedia :: Graphics :: Editors :: Vector-Based	18 :: 99 :: 100 :: 106 :: 107
105	2000031701	100	18	conversion	Graphics Conversion	Programs which convert between graphics formats.	0	0	Topic :: Multimedia :: Graphics :: Graphics Conversion	18 :: 99 :: 100 :: 105
101	2000031601	100	18	capture	Capture	Support for scanners, cameras, and screen capture.	0	0	Topic :: Multimedia :: Graphics :: Capture	18 :: 99 :: 100 :: 101
104	2000031601	101	18	screencapture	Screen Capture	Screen capture tools and processors.	0	0	Topic :: Multimedia :: Graphics :: Capture :: Screen Capture	18 :: 99 :: 100 :: 101 :: 104
103	2000031601	101	18	cameras	Digital Camera	Digital Camera	0	0	Topic :: Multimedia :: Graphics :: Capture :: Digital Camera	18 :: 99 :: 100 :: 101 :: 103
102	2000031601	101	18	scanners	Scanners	Support for graphic scanners.	0	0	Topic :: Multimedia :: Graphics :: Capture :: Scanners	18 :: 99 :: 100 :: 101 :: 102
154	2000032001	18	18	printing	Printing	Tools, daemons, and utilities for printer control.	0	0	Topic :: Printing	18 :: 154
136	2000032001	18	18	system	System	Operating system core and administration utilities.	0	0	Topic :: System	18 :: 136
294	2001032001	136	18	shells	System Shells	System Shells	0	0	Topic :: System :: System Shells	18 :: 136 :: 294
257	2000071101	136	18	softwaredist	Software Distribution	Systems software for distributing other software.	0	0	Topic :: System :: Software Distribution	18 :: 136 :: 257
253	2000071101	136	18	sysadministration	Systems Administration	Systems Administration Software (e.g. configuration apps.)	0	0	Topic :: System :: Systems Administration	18 :: 136 :: 253
289	2001032001	253	18	authentication	Authentication/Directory	Authentication and directory services	0	0	Topic :: System :: Systems Administration :: Authentication/Directory	18 :: 136 :: 253 :: 289
291	2001032001	289	18	ldap	LDAP	Leightweight directory access protocol	0	0	Topic :: System :: Systems Administration :: Authentication/Directory :: LDAP	18 :: 136 :: 253 :: 289 :: 291
290	2001032001	289	18	nis	NIS	NIS services	0	0	Topic :: System :: Systems Administration :: Authentication/Directory :: NIS	18 :: 136 :: 253 :: 289 :: 290
153	2000032001	136	18	power	Power (UPS)	Code for communication with uninterruptible power supplies.	0	0	Topic :: System :: Power (UPS)	18 :: 136 :: 153
150	2000032001	136	18	networking	Networking	Network configuration and administration.	0	0	Topic :: System :: Networking	18 :: 136 :: 150
152	2000032001	150	18	monitoring	Monitoring	System monitoring, traffic analysis, and sniffers.	0	0	Topic :: System :: Networking :: Monitoring	18 :: 136 :: 150 :: 152
155	2000032001	152	18	watchdog	Hardware Watchdog	Software to monitor and perform actions or shutdown on hardware trouble detection.	0	0	Topic :: System :: Networking :: Monitoring :: Hardware Watchdog	18 :: 136 :: 150 :: 152 :: 155
151	2000032001	150	18	firewalls	Firewalls	Firewalls and filtering systems.	0	0	Topic :: System :: Networking :: Firewalls	18 :: 136 :: 150 :: 151
148	2000032001	136	18	logging	Logging	Utilities for clearing, rotating, and digesting system logs.	0	0	Topic :: System :: Logging	18 :: 136 :: 148
147	2000032001	136	18	setup	Installation/Setup	Tools for installation and setup of the operating system and other programs.	0	0	Topic :: System :: Installation/Setup	18 :: 136 :: 147
146	2000032001	136	18	hardware	Hardware	Tools for direct, non-kernel control and configuration of hardware.	0	0	Topic :: System :: Hardware	18 :: 136 :: 146
292	2001032001	146	18	drivers	Hardware Drivers	Hardware Drivers	0	0	Topic :: System :: Hardware :: Hardware Drivers	18 :: 136 :: 146 :: 292
144	2000032001	136	18	kernels	Operating System Kernels	OS Kernels, patches, modules, and tools.	0	0	Topic :: System :: Operating System Kernels	18 :: 136 :: 144
239	2000041301	144	18	gnuhurd	GNU Hurd	Kernel code and modules for GNU Hurd.	0	0	Topic :: System :: Operating System Kernels :: GNU Hurd	18 :: 136 :: 144 :: 239
145	2000032001	144	18	bsd	BSD	Code relating to any of the BSD kernels.	0	0	Topic :: System :: Operating System Kernels :: BSD	18 :: 136 :: 144 :: 145
143	2000032001	144	18	linux	Linux	The Linux kernel, patches, and modules.	0	0	Topic :: System :: Operating System Kernels :: Linux	18 :: 136 :: 144 :: 143
142	2000032001	136	18	filesystems	Filesystems	Support for creating, editing, reading, and writing file systems.	0	0	Topic :: System :: Filesystems	18 :: 136 :: 142
287	2001032001	80	18	boardgames	Board Games	Board Games	0	0	Topic :: Games/Entertainment :: Board Games	18 :: 80 :: 287
268	2000082101	80	18	Puzzles	Puzzle Games	Puzzle Games	0	0	Topic :: Games/Entertainment :: Puzzle Games	18 :: 80 :: 268
86	2000031601	80	18	mud	Multi-User Dungeons (MUD)	Massively-multiplayer text based games.	0	0	Topic :: Games/Entertainment :: Multi-User Dungeons (MUD)	18 :: 80 :: 86
85	2000031601	80	18	simulation	Simulation	Simulation games	0	0	Topic :: Games/Entertainment :: Simulation	18 :: 80 :: 85
84	2000031601	80	18	rpg	Role-Playing	Role-Playing games	0	0	Topic :: Games/Entertainment :: Role-Playing	18 :: 80 :: 84
83	2000032401	80	18	turnbasedstrategy	Turn Based Strategy	Turn Based Strategy	0	0	Topic :: Games/Entertainment :: Turn Based Strategy	18 :: 80 :: 83
82	2000031601	80	18	firstpersonshooters	First Person Shooters	First Person Shooters.	0	0	Topic :: Games/Entertainment :: First Person Shooters	18 :: 80 :: 82
81	2000031601	80	18	realtimestrategy	Real Time Strategy	Real Time strategy games	0	0	Topic :: Games/Entertainment :: Real Time Strategy	18 :: 80 :: 81
288	2001032001	80	18	sidescrolling	Side-Scrolling/Arcade Games	Arcade-style side-scrolling games	0	0	Topic :: Games/Entertainment :: Side-Scrolling/Arcade Games	18 :: 80 :: 288
71	2000031601	18	18	education	Education	Programs and tools for educating yourself or others.	0	0	Topic :: Education	18 :: 71
73	2000031601	71	18	testing	Testing	Tools for testing someone's knowledge on a subject.	0	0	Topic :: Education :: Testing	18 :: 71 :: 73
72	2000031601	71	18	cai	Computer Aided Instruction (CAI)	Programs for authoring or using Computer Aided Instrution courses.	0	0	Topic :: Education :: Computer Aided Instruction (CAI)	18 :: 71 :: 72
66	2000031601	18	18	database	Database	Front ends, engines, and tools for database work.	0	0	Topic :: Database	18 :: 66
68	2000031601	66	18	frontends	Front-Ends	Clients and front-ends for generating queries to database engines.	0	0	Topic :: Database :: Front-Ends	18 :: 66 :: 68
67	2000031601	66	18	engines	Database Engines/Servers	Programs that manage data and provide control via some query language.	0	0	Topic :: Database :: Database Engines/Servers	18 :: 66 :: 67
63	2000032001	18	18	editors	Text Editors	Programs for editing code and documents.	0	0	Topic :: Text Editors	18 :: 63
285	2001032001	63	18	textprocessing	Text Processing	Programs or libraries that are designed to batch process text documents	0	0	Topic :: Text Editors :: Text Processing	18 :: 63 :: 285
70	2000031601	63	18	wordprocessors	Word Processors	WYSIWYG word processors.	0	0	Topic :: Text Editors :: Word Processors	18 :: 63 :: 70
69	2000031601	63	18	documentation	Documentation	Tools for the creation and use of documentation.	0	0	Topic :: Text Editors :: Documentation	18 :: 63 :: 69
65	2000031601	63	18	ide	Integrated Development Environments (IDE)	Complete editing environments for code, including cababilities such as compilation and code building assistance.	0	0	Topic :: Text Editors :: Integrated Development Environments (IDE)	18 :: 63 :: 65
64	2000031601	63	18	emacs	Emacs	GNU Emacs and its imitators and tools.	0	0	Topic :: Text Editors :: Emacs	18 :: 63 :: 64
125	2000031701	99	18	video	Video	Video capture, editing, and playback.	0	0	Topic :: Multimedia :: Video	18 :: 99 :: 125
126	2000031701	125	18	capture	Capture	Video capture tools.	0	0	Topic :: Multimedia :: Video :: Capture	18 :: 99 :: 125 :: 126
256	2000071101	125	18	nonlineareditor	Non-Linear Editor	Video Non-Linear Editors	0	0	Topic :: Multimedia :: Video :: Non-Linear Editor	18 :: 99 :: 125 :: 256
128	2000031701	125	18	display	Display	Programs which display various video formats.	0	0	Topic :: Multimedia :: Video :: Display	18 :: 99 :: 125 :: 128
127	2000031701	125	18	conversion	Conversion	Programs which convert between video formats.	0	0	Topic :: Multimedia :: Video :: Conversion	18 :: 99 :: 125 :: 127
113	2000031701	99	18	sound	Sound/Audio	Tools for generating, editing, analyzing, and playing sound.	0	0	Topic :: Multimedia :: Sound/Audio	18 :: 99 :: 113
249	2000042801	113	18	synthesis	Sound Synthesis	Software for creation and synthesis of sound.	0	0	Topic :: Multimedia :: Sound/Audio :: Sound Synthesis	18 :: 99 :: 113 :: 249
248	2000042801	113	18	midi	MIDI	Software related to MIDI synthesis and playback.	0	0	Topic :: Multimedia :: Sound/Audio :: MIDI	18 :: 99 :: 113 :: 248
124	2000031701	113	18	speech	Speech	Speech manipulation and intepretation tools.	0	0	Topic :: Multimedia :: Sound/Audio :: Speech	18 :: 99 :: 113 :: 124
122	2000031701	113	18	players	Players	Programs to play audio files to a sound device.	0	0	Topic :: Multimedia :: Sound/Audio :: Players	18 :: 99 :: 113 :: 122
123	2000031701	122	18	mp3	MP3	Programs to play MP3 audio files.	0	0	Topic :: Multimedia :: Sound/Audio :: Players :: MP3	18 :: 99 :: 113 :: 122 :: 123
121	2000031701	113	18	mixers	Mixers	Programs to mix audio.	0	0	Topic :: Multimedia :: Sound/Audio :: Mixers	18 :: 99 :: 113 :: 121
120	2000031701	113	18	editors	Editors	Programs to edit/manipulate sound data.	0	0	Topic :: Multimedia :: Sound/Audio :: Editors	18 :: 99 :: 113 :: 120
119	2000031701	113	18	conversion	Conversion	Programs to convert between audio formats.	0	0	Topic :: Multimedia :: Sound/Audio :: Conversion	18 :: 99 :: 113 :: 119
116	2000031701	113	18	cdaudio	CD Audio	Programs to play and manipulate audio CDs.	0	0	Topic :: Multimedia :: Sound/Audio :: CD Audio	18 :: 99 :: 113 :: 116
88	2000031601	87	18	finger	Finger	The Finger protocol for getting information about users.	0	0	Topic :: Internet :: Finger	18 :: 87 :: 88
118	2000031701	116	18	cdripping	CD Ripping	Software to convert CD Audio to other digital formats.	0	0	Topic :: Multimedia :: Sound/Audio :: CD Audio :: CD Ripping	18 :: 99 :: 113 :: 116 :: 118
117	2000031701	116	18	cdplay	CD Playing	CD Playing software, to include jukebox software.	0	0	Topic :: Multimedia :: Sound/Audio :: CD Audio :: CD Playing	18 :: 99 :: 113 :: 116 :: 117
260	2000071401	52	18	SCCS	SCCS	SCCS	0	0	Topic :: Software Development :: Version Control :: SCCS	18 :: 45 :: 52 :: 260
54	2000031601	52	18	rcs	RCS	Tools for RCS (Revision Control System).	0	0	Topic :: Software Development :: Version Control :: RCS	18 :: 45 :: 52 :: 54
53	2000031601	52	18	cvs	CVS	Tools for CVS (Concurrent Versioning System).	0	0	Topic :: Software Development :: Version Control :: CVS	18 :: 45 :: 52 :: 53
50	2000031601	45	18	objectbrokering	Object Brokering	Object brokering libraries and tools.	0	0	Topic :: Software Development :: Object Brokering	18 :: 45 :: 50
51	2000031601	50	18	corba	CORBA	Tools for implementation and use of CORBA.	0	0	Topic :: Software Development :: Object Brokering :: CORBA	18 :: 45 :: 50 :: 51
49	2000031601	45	18	interpreters	Interpreters	Programs for interpreting and executing high-level languages directly.	0	0	Topic :: Software Development :: Interpreters	18 :: 45 :: 49
48	2000031601	45	18	compilers	Compilers	Programs for compiling high-level languges into machine code.	0	0	Topic :: Software Development :: Compilers	18 :: 45 :: 48
47	2000031601	45	18	debuggers	Debuggers	Programs for controlling and monitoring the execution of compiled binaries.	0	0	Topic :: Software Development :: Debuggers	18 :: 45 :: 47
46	2000031601	45	18	build	Build Tools	Software for the build process.	0	0	Topic :: Software Development :: Build Tools	18 :: 45 :: 46
43	2000031601	18	18	security	Security	Security-related software, to include system administration and cryptography.	0	0	Topic :: Security	18 :: 43
44	2000031601	43	18	cryptography	Cryptography	Cryptography programs, algorithms, and libraries.	0	0	Topic :: Security :: Cryptography	18 :: 43 :: 44
97	2000042701	18	18	scientific	Scientific/Engineering	Scientific applications, to include research, applied and pure mathematics and sciences.	0	0	Topic :: Scientific/Engineering	18 :: 97
98	2000031601	97	18	mathematics	Mathematics	Software to support pure and applied mathematics.	0	0	Topic :: Scientific/Engineering :: Mathematics	18 :: 97 :: 98
272	2000100501	97	18	HMI	Human Machine Interfaces	This applies to the Factory/Machine control/Automation fields where there are already thousands of applications and millions of installations.	0	0	Topic :: Scientific/Engineering :: Human Machine Interfaces	18 :: 97 :: 272
266	2000081601	97	18	medical	Medical Science Apps.	Medical / BioMedical Science Apps.	0	0	Topic :: Scientific/Engineering :: Medical Science Apps.	18 :: 97 :: 266
252	2000071101	97	18	bioinformatics	Bio-Informatics	Category for gene software (e.g. Gene Ontology)	0	0	Topic :: Scientific/Engineering :: Bio-Informatics	18 :: 97 :: 252
246	2000042701	97	18	eda	Electronic Design Automation (EDA)	Tools for circuit design, schematics, board layout, and more.	0	0	Topic :: Scientific/Engineering :: Electronic Design Automation (EDA)	18 :: 97 :: 246
135	2000032001	97	18	visualization	Visualization	Software for scientific visualization.	0	0	Topic :: Scientific/Engineering :: Visualization	18 :: 97 :: 135
134	2000032001	97	18	astronomy	Astronomy	Software and tools related to astronomy.	0	0	Topic :: Scientific/Engineering :: Astronomy	18 :: 97 :: 134
133	2000032001	97	18	ai	Artificial Intelligence	Artificial Intelligence.	0	0	Topic :: Scientific/Engineering :: Artificial Intelligence	18 :: 97 :: 133
87	2000031601	18	18	internet	Internet	Tools to assist human access to the Internet.	0	0	Topic :: Internet	18 :: 87
270	2000083101	87	18	WAP	WAP	Wireless Access Protocol	0	0	Topic :: Internet :: WAP	18 :: 87 :: 270
245	2000042701	87	18	loganalysis	Log Analysis	Software to help analyze various log files.	0	0	Topic :: Internet :: Log Analysis	18 :: 87 :: 245
149	2000032001	87	18	dns	Name Service (DNS)	Domain name system servers and utilities.	0	0	Topic :: Internet :: Name Service (DNS)	18 :: 87 :: 149
90	2000031601	87	18	www	WWW/HTTP	Programs and tools for the World Wide Web.	0	0	Topic :: Internet :: WWW/HTTP	18 :: 87 :: 90
250	2000042801	90	18	httpservers	HTTP Servers	Software designed to serve content via the HTTP protocol.	0	0	Topic :: Internet :: WWW/HTTP :: HTTP Servers	18 :: 87 :: 90 :: 250
243	2000042701	90	18	sitemanagement	Site Management	Tools for maintanance and management of web sites.	0	0	Topic :: Internet :: WWW/HTTP :: Site Management	18 :: 87 :: 90 :: 243
244	2000042701	243	18	linkchecking	Link Checking	Tools to assist in checking for broken links.	0	0	Topic :: Internet :: WWW/HTTP :: Site Management :: Link Checking	18 :: 87 :: 90 :: 243 :: 244
93	2000031601	90	18	indexing	Indexing/Search	Indexing and search tools for the Web.	0	0	Topic :: Internet :: WWW/HTTP :: Indexing/Search	18 :: 87 :: 90 :: 93
92	2000031601	90	18	dynamic	Dynamic Content	Common Gateway Interface scripting and server-side parsing.	0	0	Topic :: Internet :: WWW/HTTP :: Dynamic Content	18 :: 87 :: 90 :: 92
96	2000031601	92	18	cgi	CGI Tools/Libraries	Tools for the Common Gateway Interface	0	0	Topic :: Internet :: WWW/HTTP :: Dynamic Content :: CGI Tools/Libraries	18 :: 87 :: 90 :: 92 :: 96
95	2000031601	92	18	messageboards	Message Boards	Online message boards	0	0	Topic :: Internet :: WWW/HTTP :: Dynamic Content :: Message Boards	18 :: 87 :: 90 :: 92 :: 95
94	2000031601	92	18	counters	Page Counters	Scripts to count numbers of pageviews.	0	0	Topic :: Internet :: WWW/HTTP :: Dynamic Content :: Page Counters	18 :: 87 :: 90 :: 92 :: 94
91	2000031601	90	18	browsers	Browsers	Web Browsers	0	0	Topic :: Internet :: WWW/HTTP :: Browsers	18 :: 87 :: 90 :: 91
89	2000031601	87	18	ftp	File Transfer Protocol (FTP)	Programs and tools for file transfer via FTP.	0	0	Topic :: Internet :: File Transfer Protocol (FTP)	18 :: 87 :: 89
80	2000031601	18	18	games	Games/Entertainment	Games and Entertainment software.	0	0	Topic :: Games/Entertainment	18 :: 80
18	2000031601	0	0	topic	Topic	Topic categorization.	0	0	Topic	18
20	2000032401	18	18	communications	Communications	Programs intended to facilitate communication between people.	0	0	Topic :: Communications	18 :: 20
27	2000031601	20	18	conferencing	Conferencing	Software to support real-time conferencing over the Internet.	0	0	Topic :: Communications :: Conferencing	18 :: 20 :: 27
22	2000031601	20	18	chat	Chat	Programs to support real-time communication over the Internet.	0	0	Topic :: Communications :: Chat	18 :: 20 :: 22
26	2000031601	22	18	aim	AOL Instant Messanger	Programs to support AOL Instant Messanger.	0	0	Topic :: Communications :: Chat :: AOL Instant Messanger	18 :: 20 :: 22 :: 26
25	2000031601	22	18	talk	Unix Talk	Programs to support Unix Talk protocol.	0	0	Topic :: Communications :: Chat :: Unix Talk	18 :: 20 :: 22 :: 25
24	2000041101	22	18	irc	Internet Relay Chat	Programs to support Internet Relay Chat.	0	0	Topic :: Communications :: Chat :: Internet Relay Chat	18 :: 20 :: 22 :: 24
23	2000031601	22	18	icq	ICQ	Programs to support ICQ.	0	0	Topic :: Communications :: Chat :: ICQ	18 :: 20 :: 22 :: 23
21	2000031601	20	18	bbs	BBS	Bulletin Board systems.	0	0	Topic :: Communications :: BBS	18 :: 20 :: 21
251	2000050101	20	18	filesharing	File Sharing	Software for person-to-person online file sharing.	0	0	Topic :: Communications :: File Sharing	18 :: 20 :: 251
241	2000050101	251	18	napster	Napster	Clients and servers for the Napster file sharing protocol.	0	0	Topic :: Communications :: File Sharing :: Napster	18 :: 20 :: 251 :: 241
286	2001032001	251	18	gnutella	Gnutella	Projects based around the gnutella protocol.	0	0	Topic :: Communications :: File Sharing :: Gnutella	18 :: 20 :: 251 :: 286
247	2000042701	20	18	telephony	Telephony	Telephony related applications, to include automated voice response systems.	0	0	Topic :: Communications :: Telephony	18 :: 20 :: 247
40	2000031601	20	18	internetphone	Internet Phone	Software to support real-time speech communication over the Internet.	0	0	Topic :: Communications :: Internet Phone	18 :: 20 :: 40
39	2000031601	20	18	usenet	Usenet News	Software to support USENET news.	0	0	Topic :: Communications :: Usenet News	18 :: 20 :: 39
38	2000031601	20	18	hamradio	Ham Radio	Tools and resources for amateur radio.	0	0	Topic :: Communications :: Ham Radio	18 :: 20 :: 38
37	2000031601	20	18	fido	FIDO	Tools for FIDOnet mail and echoes.	0	0	Topic :: Communications :: FIDO	18 :: 20 :: 37
36	2000031601	20	18	fax	Fax	Tools for sending and receiving facsimile messages.	0	0	Topic :: Communications :: Fax	18 :: 20 :: 36
28	2000031601	20	18	email	Email	Programs for sending, processing, and handling electronic mail.	0	0	Topic :: Communications :: Email	18 :: 20 :: 28
33	2000031601	28	18	postoffice	Post-Office	Programs to support post-office protocols, including POP and IMAP.	0	0	Topic :: Communications :: Email :: Post-Office	18 :: 20 :: 28 :: 33
35	2000031601	33	18	imap	IMAP	Programs to support IMAP protocol (Internet Message Access Protocol).	0	0	Topic :: Communications :: Email :: Post-Office :: IMAP	18 :: 20 :: 28 :: 33 :: 35
34	2000031601	33	18	pop3	POP3	Programs to support POP3 (Post-Office Protocol, version 3).	0	0	Topic :: Communications :: Email :: Post-Office :: POP3	18 :: 20 :: 28 :: 33 :: 34
32	2000031601	28	18	mta	Mail Transport Agents	Email transport and gatewaying software.	0	0	Topic :: Communications :: Email :: Mail Transport Agents	18 :: 20 :: 28 :: 32
31	2000031601	28	18	mua	Email Clients (MUA)	Programs for interactively reading and sending Email.	0	0	Topic :: Communications :: Email :: Email Clients (MUA)	18 :: 20 :: 28 :: 31
30	2000031601	28	18	listservers	Mailing List Servers	Tools for managing electronic mailing lists.	0	0	Topic :: Communications :: Email :: Mailing List Servers	18 :: 20 :: 28 :: 30
29	2000031601	28	18	filters	Filters	Content-driven filters and dispatchers for Email.	0	0	Topic :: Communications :: Email :: Filters	18 :: 20 :: 28 :: 29
301	2001041701	14	13	nosl	Nokia Open Source License	Nokia Open Source License	0	0	License :: OSI Approved :: Nokia Open Source License	13 :: 14 :: 301
299	2001041701	14	13	iosl	Intel Open Source License	Intel Open Source License	0	0	License :: OSI Approved :: Intel Open Source License	13 :: 14 :: 299
297	2001041701	14	13	vsl	Vovida Software License	Vovida Software License	0	0	License :: OSI Approved :: Vovida Software License	13 :: 14 :: 297
195	2000032001	14	13	zlib	zlib/libpng License	zlib/libpng License	0	0	License :: OSI Approved :: zlib/libpng License	13 :: 14 :: 195
194	2000032001	14	13	python	Python License	Python License	0	0	License :: OSI Approved :: Python License	13 :: 14 :: 194
193	2000032001	14	13	ricoh	Ricoh Source Code Public License	Ricoh Source Code Public License	0	0	License :: OSI Approved :: Ricoh Source Code Public License	13 :: 14 :: 193
192	2000032001	14	13	cvw	MITRE Collaborative Virtual Workspace License (CVW)	MITRE Collaborative Virtual Workspace License (CVW)	0	0	License :: OSI Approved :: MITRE Collaborative Virtual Workspace License (CVW)	13 :: 14 :: 192
191	2000032001	14	13	ibm	IBM Public License	IBM Public License	0	0	License :: OSI Approved :: IBM Public License	13 :: 14 :: 191
190	2000032001	14	13	qpl	QT Public License (QPL)	QT Public License	0	0	License :: OSI Approved :: QT Public License (QPL)	13 :: 14 :: 190
189	2000032001	14	13	mpl	Mozilla Public License (MPL)	Mozilla Public License (MPL)	0	0	License :: OSI Approved :: Mozilla Public License (MPL)	13 :: 14 :: 189
305	2001041701	189	13	mpl11	Mozilla Public License 1.1	Mozilla Public License 1.1	0	0	License :: OSI Approved :: Mozilla Public License (MPL) :: Mozilla Public License 1.1	13 :: 14 :: 189 :: 305
199	2000032101	0	0	os	Operating System	What operating system the program requires to run, if any.	0	0	Operating System	199
200	2000032101	199	199	posix	POSIX	POSIX plus standard Berkeley socket facilities. Don't list a more specific OS unless your program requires it.	0	0	Operating System :: POSIX	199 :: 200
201	2000032101	200	199	linux	Linux	Any version of Linux. Don't specify a subcategory unless the program requires a particular distribution.	0	0	Operating System :: POSIX :: Linux	199 :: 200 :: 201
202	2000032101	200	199	bsd	BSD	Any variant of BSD. Don't specify a subcategory unless the program requires a particular BSD flavor.	0	0	Operating System :: POSIX :: BSD	199 :: 200 :: 202
203	2000041101	202	199	freebsd	FreeBSD	FreeBSD	0	0	Operating System :: POSIX :: BSD :: FreeBSD	199 :: 200 :: 202 :: 203
204	2000032101	202	199	netbsd	NetBSD	NetBSD	0	0	Operating System :: POSIX :: BSD :: NetBSD	199 :: 200 :: 202 :: 204
205	2000032101	202	199	openbsd	OpenBSD	OpenBSD	0	0	Operating System :: POSIX :: BSD :: OpenBSD	199 :: 200 :: 202 :: 205
206	2000032101	202	199	bsdos	BSD/OS	BSD/OS	0	0	Operating System :: POSIX :: BSD :: BSD/OS	199 :: 200 :: 202 :: 206
207	2000032101	200	199	sun	SunOS/Solaris	Any Sun Microsystems OS.	0	0	Operating System :: POSIX :: SunOS/Solaris	199 :: 200 :: 207
208	2000032101	200	199	sco	SCO	SCO	0	0	Operating System :: POSIX :: SCO	199 :: 200 :: 208
209	2000032101	200	199	hpux	HP-UX	HP-UX	0	0	Operating System :: POSIX :: HP-UX	199 :: 200 :: 209
210	2000032101	200	199	aix	AIX	AIX	0	0	Operating System :: POSIX :: AIX	199 :: 200 :: 210
211	2000032101	200	199	irix	IRIX	IRIX	0	0	Operating System :: POSIX :: IRIX	199 :: 200 :: 211
212	2000032101	200	199	other	Other	Other specific POSIX OS, specified in description.	0	0	Operating System :: POSIX :: Other	199 :: 200 :: 212
282	2000121901	18	18	Sociology	Sociology	Social / Informational - Family / etc.	0	0	Topic :: Sociology	18 :: 282
214	2000032101	199	199	microsoft	Microsoft	Microsoft operating systems.	0	0	Operating System :: Microsoft	199 :: 214
215	2000032101	214	199	msdos	MS-DOS	Microsoft Disk Operating System (DOS)	0	0	Operating System :: Microsoft :: MS-DOS	199 :: 214 :: 215
216	2000032101	214	199	windows	Windows	Windows software, not specific to any particular version of Windows.	0	0	Operating System :: Microsoft :: Windows	199 :: 214 :: 216
217	2000032101	216	199	win31	Windows 3.1 or Earlier	Windows 3.1 or Earlier	0	0	Operating System :: Microsoft :: Windows :: Windows 3.1 or Earlier	199 :: 214 :: 216 :: 217
218	2000032101	216	199	win95	Windows 95/98/2000	Windows 95, Windows 98, and Windows 2000.	0	0	Operating System :: Microsoft :: Windows :: Windows 95/98/2000	199 :: 214 :: 216 :: 218
219	2000041101	216	199	winnt	Windows NT/2000	Windows NT and Windows 2000.	0	0	Operating System :: Microsoft :: Windows :: Windows NT/2000	199 :: 214 :: 216 :: 219
220	2000032101	199	199	os2	OS/2	OS/2	0	0	Operating System :: OS/2	199 :: 220
221	2000032101	199	199	macos	MacOS	MacOS	0	0	Operating System :: MacOS	199 :: 221
222	2000032101	216	199	wince	Windows CE	Windows CE	0	0	Operating System :: Microsoft :: Windows :: Windows CE	199 :: 214 :: 216 :: 222
223	2000032101	199	199	palmos	PalmOS	PalmOS (for Palm Pilot)	0	0	Operating System :: PalmOS	199 :: 223
224	2000032101	199	199	beos	BeOS	BeOS	0	0	Operating System :: BeOS	199 :: 224
225	2000032101	0	0	environment	Environment	Run-time environment required for this program.	0	0	Environment	225
226	2000041101	225	225	console	Console (Text Based)	Console-based programs.	0	0	Environment :: Console (Text Based)	225 :: 226
227	2000032401	226	225	curses	Curses	Curses-based software.	0	0	Environment :: Console (Text Based) :: Curses	225 :: 226 :: 227
228	2000040701	226	225	newt	Newt	Newt	0	0	Environment :: Console (Text Based) :: Newt	225 :: 226 :: 228
229	2000040701	225	225	x11	X11 Applications	Programs that run in an X windowing environment.	0	0	Environment :: X11 Applications	225 :: 229
230	2000040701	225	225	win32	Win32 (MS Windows)	Programs designed to run in a graphical Microsoft Windows environment.	0	0	Environment :: Win32 (MS Windows)	225 :: 230
231	2000040701	229	225	gnome	Gnome	Programs designed to run in a Gnome environment.	0	0	Environment :: X11 Applications :: Gnome	225 :: 229 :: 231
232	2000040701	229	225	kde	KDE	Programs designed to run in a KDE environment.	0	0	Environment :: X11 Applications :: KDE	225 :: 229 :: 232
233	2000040701	225	225	other	Other Environment	Programs designed to run in an environment other than one listed.	0	0	Environment :: Other Environment	225 :: 233
283	2000121901	282	18	History	History	History / Informational	0	0	Topic :: Sociology :: History	18 :: 282 :: 283
235	2000041001	199	199	independent	OS Independent	This software does not depend on any particular operating system.	0	0	Operating System :: OS Independent	199 :: 235
236	2000040701	199	199	other	Other OS	Program is designe for a nonlisted operating system.	0	0	Operating System :: Other OS	199 :: 236
237	2000041001	225	225	web	Web Environment	This software is designed for a web environment.	0	0	Environment :: Web Environment	225 :: 237
238	2000041101	225	225	daemon	No Input/Output (Daemon)	This program has no input or output, but is intended to run in the background as a daemon.	0	0	Environment :: No Input/Output (Daemon)	225 :: 238
284	2000121901	282	18	Genealogy	Genealogy	Family History / Genealogy	0	0	Topic :: Sociology :: Genealogy	18 :: 282 :: 284
240	2000041301	200	199	gnuhurd	GNU Hurd	GNU Hurd	0	0	Operating System :: POSIX :: GNU Hurd	199 :: 200 :: 240
55	2000031601	18	18	desktop	Desktop Environment	Accessories, managers, and utilities for your GUI desktop.	0	0	Topic :: Desktop Environment	18 :: 55
58	2000031601	55	18	gnome	Gnome	Software for the Gnome desktop.	0	0	Topic :: Desktop Environment :: Gnome	18 :: 55 :: 58
57	2000031601	55	18	kde	K Desktop Environment (KDE)	Software for the KDE desktop.	0	0	Topic :: Desktop Environment :: K Desktop Environment (KDE)	18 :: 55 :: 57
61	2000031601	57	18	themes	Themes	Themes for KDE.	0	0	Topic :: Desktop Environment :: K Desktop Environment (KDE) :: Themes	18 :: 55 :: 57 :: 61
56	2000031601	55	18	windowmanagers	Window Managers	Programs that provide window control and application launching.	0	0	Topic :: Desktop Environment :: Window Managers	18 :: 55 :: 56
59	2000031601	56	18	enlightenment	Enlightenment	Software for the Enlightenment window manager.	0	0	Topic :: Desktop Environment :: Window Managers :: Enlightenment	18 :: 55 :: 56 :: 59
60	2000031601	59	18	themes	Themes	Themes for the Enlightenment window manager.	0	0	Topic :: Desktop Environment :: Window Managers :: Enlightenment :: Themes	18 :: 55 :: 56 :: 59 :: 60
62	2000031601	55	18	screensavers	Screen Savers	Screen savers and lockers.	0	0	Topic :: Desktop Environment :: Screen Savers	18 :: 55 :: 62
259	2000071401	45	18	codegen	Code Generators	Code Generators	0	0	Topic :: Software Development :: Code Generators	18 :: 45 :: 259
52	2000031601	45	18	versioncontrol	Version Control	Tools for managing multiple versions of evolving sources or documents.	0	0	Topic :: Software Development :: Version Control	18 :: 45 :: 52
160	2000032001	0	0	language	Programming Language	Language in which this program was written, or was meant to support.	0	0	Programming Language	160
161	2000032001	160	160	apl	APL	APL	0	0	Programming Language :: APL	160 :: 161
164	2000032001	160	160	c	C	C	0	0	Programming Language :: C	160 :: 164
162	2000032001	160	160	assembly	Assembly	Assembly-level programs. Platform specific.	0	0	Programming Language :: Assembly	160 :: 162
163	2000051001	160	160	ada	Ada	Ada	0	0	Programming Language :: Ada	160 :: 163
165	2000032001	160	160	cpp	C++	C++	0	0	Programming Language :: C++	160 :: 165
166	2000032401	160	160	eiffel	Eiffel	Eiffel	0	0	Programming Language :: Eiffel	160 :: 166
167	2000032001	160	160	euler	Euler	Euler	0	0	Programming Language :: Euler	160 :: 167
168	2000032001	160	160	forth	Forth	Forth	0	0	Programming Language :: Forth	160 :: 168
169	2000032001	160	160	fortran	Fortran	Fortran	0	0	Programming Language :: Fortran	160 :: 169
170	2000032001	160	160	lisp	Lisp	Lisp	0	0	Programming Language :: Lisp	160 :: 170
171	2000041101	160	160	logo	Logo	Logo	0	0	Programming Language :: Logo	160 :: 171
172	2000032001	160	160	ml	ML	ML	0	0	Programming Language :: ML	160 :: 172
173	2000032001	160	160	modula	Modula	Modula-2 or Modula-3	0	0	Programming Language :: Modula	160 :: 173
174	2000032001	160	160	objectivec	Objective C	Objective C	0	0	Programming Language :: Objective C	160 :: 174
175	2000032001	160	160	pascal	Pascal	Pascal	0	0	Programming Language :: Pascal	160 :: 175
176	2000032001	160	160	perl	Perl	Perl	0	0	Programming Language :: Perl	160 :: 176
177	2000032001	160	160	prolog	Prolog	Prolog	0	0	Programming Language :: Prolog	160 :: 177
178	2000032001	160	160	python	Python	Python	0	0	Programming Language :: Python	160 :: 178
179	2000032001	160	160	rexx	Rexx	Rexx	0	0	Programming Language :: Rexx	160 :: 179
180	2000032001	160	160	simula	Simula	Simula	0	0	Programming Language :: Simula	160 :: 180
181	2000032001	160	160	smalltalk	Smalltalk	Smalltalk	0	0	Programming Language :: Smalltalk	160 :: 181
182	2000032001	160	160	tcl	Tcl	Tcl	0	0	Programming Language :: Tcl	160 :: 182
183	2000032001	160	160	php	PHP	PHP	0	0	Programming Language :: PHP	160 :: 183
263	2000080401	160	160	euphoria	Euphoria	Euphoria programming language - http://www.rapideuphoria.com/	0	0	Programming Language :: Euphoria	160 :: 263
264	2000080701	160	160	erlang	Erlang	Erlang - developed by Ericsson - http://www.erlang.org/	0	0	Programming Language :: Erlang	160 :: 264
267	2000082001	160	160	zope	Zope	Zope Object Publishing	0	0	Programming Language :: Zope	160 :: 267
269	2000082801	160	160	asm	Assembly	ASM programming	0	0	Programming Language :: Assembly	160 :: 269
271	2000092001	160	160	csharp	C#	Microsoft's C++/Java Language	0	0	Programming Language :: C#	160 :: 271
273	2000102001	160	160	Pike	Pike	Pike, see http://pike.roxen.com/.	0	0	Programming Language :: Pike	160 :: 273
184	2000032001	160	160	asp	ASP	Active Server Pages	0	0	Programming Language :: ASP	160 :: 184
185	2000032001	160	160	shell	Unix Shell	Unix Shell	0	0	Programming Language :: Unix Shell	160 :: 185
186	2000032001	160	160	visualbasic	Visual Basic	Visual Basic	0	0	Programming Language :: Visual Basic	160 :: 186
198	2000032001	160	160	java	Java	Java	0	0	Programming Language :: Java	160 :: 198
213	2000032101	160	160	other	Other	Other programming language, specified in description.	0	0	Programming Language :: Other	160 :: 213
242	2000042701	160	160	scheme	Scheme	Scheme programming language.	0	0	Programming Language :: Scheme	160 :: 242
254	2000071101	160	160	plsql	PL/SQL	PL/SQL Programming Language	0	0	Programming Language :: PL/SQL	160 :: 254
255	2000071101	160	160	progress	PROGRESS	PROGRESS Programming Language	0	0	Programming Language :: PROGRESS	160 :: 255
258	2000071101	160	160	objectpascal	Object Pascal	Object Pascal	0	0	Programming Language :: Object Pascal	160 :: 258
261	2000072501	160	160	xbasic	XBasic	XBasic programming language	0	0	Programming Language :: XBasic	160 :: 261
262	2000073101	160	160	coldfusion	Cold Fusion	Cold Fusion Language	0	0	Programming Language :: Cold Fusion	160 :: 262
304	2001041701	189	13	mpl10	Mozilla Public License 1.0	Mozilla Public License 1.0	0	0	License :: OSI Approved :: Mozilla Public License (MPL) :: Mozilla Public License 1.0	13 :: 14 :: 189 :: 304
188	2000032001	14	13	mit	MIT/X Consortium License	MIT License, also the X Consortium License.	0	0	License :: OSI Approved :: MIT/X Consortium License	13 :: 14 :: 188
187	2000032001	14	13	bsd	BSD License	BSD License	0	0	License :: OSI Approved :: BSD License	13 :: 14 :: 187
17	2000032001	14	13	artistic	Artistic License	The Perl Artistic License	0	0	License :: OSI Approved :: Artistic License	13 :: 14 :: 17
16	2000050801	14	13	lgpl	GNU Lesser General Public License (LGPL)	GNU Lesser General Public License	0	0	License :: OSI Approved :: GNU Lesser General Public License (LGPL)	13 :: 14 :: 16
15	2000032001	14	13	gpl	GNU General Public License (GPL)	GNU General Public License.	0	0	License :: OSI Approved :: GNU General Public License (GPL)	13 :: 14 :: 15
280	2000110101	160	160	JavaScript	JavaScript	Java Scripting Language	0	0	Programming Language :: JavaScript	160 :: 280
281	2000111401	160	160	REBOL	REBOL	REBOL Programming Language	0	0	Programming Language :: REBOL	160 :: 281
265	2001032001	160	160	Delphi	Delphi/Kylix	Borland/Inprise Delphi or other Object-Pascal based languages	0	0	Programming Language :: Delphi/Kylix	160 :: 265
293	2001032001	160	160	ruby	Ruby	Ruby programming language	0	0	Programming Language :: Ruby	160 :: 293
45	2000031601	18	18	development	Software Development	Software used to aid software development.	0	0	Topic :: Software Development	18 :: 45
99	2000031601	18	18	multimedia	Multimedia	Graphics, sound, video, and multimedia.	0	0	Topic :: Multimedia	18 :: 99
296	2001041701	14	13	asl	Apache Software License	Apache Software License	0	0	License :: OSI Approved :: Apache Software License	13 :: 14 :: 296
274	2000102401	0	0	natlanguage	Natural Language	The oral/written language for the development and use of this software.	0	0	Natural Language	274
295	2001040601	274	274	russian	Russian	Projects having something to do with Russian Language	0	0	Natural Language :: Russian	274 :: 295
275	2000102401	274	274	english	English	English	0	0	Natural Language :: English	274 :: 275
276	2000102401	274	274	french	French	French	0	0	Natural Language :: French	274 :: 276
277	2000102401	274	274	spanish	Spanish	Spanish	0	0	Natural Language :: Spanish	274 :: 277
278	2000102601	274	274	japanese	Japanese	Projects using the Japanese language	0	0	Natural Language :: Japanese	274 :: 278
279	2000102601	274	274	german	German	Projects using the German language	0	0	Natural Language :: German	274 :: 279
298	2001041701	14	13	sissl	Sun Internet Standards Source License	Sun Internet Standards Source License	0	0	License :: OSI Approved :: Sun Internet Standards Source License	13 :: 14 :: 298
300	2001041701	14	13	josl	Jabber Open Source License	Jabber Open Source License	0	0	License :: OSI Approved :: Jabber Open Source License	13 :: 14 :: 300
302	2001041701	14	13	sleepycat	Sleepycat License	Sleepycat License	0	0	License :: OSI Approved :: Sleepycat License	13 :: 14 :: 302
\.


--
-- Name: trove_cat_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('trove_cat_pk_seq', 305, true);


--
-- Data for Name: trove_group_link; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY trove_group_link (trove_group_id, trove_cat_id, trove_cat_version, group_id, trove_cat_root) FROM stdin;
\.


--
-- Name: trove_group_link_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('trove_group_link_pk_seq', 1, false);


--
-- Name: trove_treesum_trove_treesum_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('trove_treesum_trove_treesum_seq', 1, false);


--
-- Data for Name: trove_treesums; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY trove_treesums (trove_treesums_id, trove_cat_id, limit_1, subprojects) FROM stdin;
\.


--
-- Name: trove_treesums_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('trove_treesums_pk_seq', 1, false);


--
-- Name: unix_uid_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('unix_uid_seq', 1, false);


--
-- Data for Name: user_bookmarks; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY user_bookmarks (bookmark_id, user_id, bookmark_url, bookmark_title) FROM stdin;
\.


--
-- Name: user_bookmarks_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('user_bookmarks_pk_seq', 1, false);


--
-- Data for Name: user_diary; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY user_diary (id, user_id, date_posted, summary, details, is_public) FROM stdin;
\.


--
-- Data for Name: user_diary_monitor; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY user_diary_monitor (monitor_id, monitored_user, user_id) FROM stdin;
\.


--
-- Name: user_diary_monitor_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('user_diary_monitor_pk_seq', 1, false);


--
-- Name: user_diary_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('user_diary_pk_seq', 1, false);


--
-- Data for Name: user_group; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY user_group (user_group_id, user_id, group_id, admin_flags, forum_flags, project_flags, doc_flags, cvs_flags, member_role, release_flags, artifact_flags, role_id) FROM stdin;
\.


--
-- Name: user_group_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('user_group_pk_seq', 1, true);


--
-- Data for Name: user_metric; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY user_metric (ranking, user_id, times_ranked, avg_raters_importance, avg_rating, metric, percentile, importance_factor) FROM stdin;
\.


--
-- Data for Name: user_metric0; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY user_metric0 (ranking, user_id, times_ranked, avg_raters_importance, avg_rating, metric, percentile, importance_factor) FROM stdin;
\.


--
-- Name: user_metric0_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('user_metric0_pk_seq', 1, false);


--
-- Data for Name: user_metric_history; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY user_metric_history (month, day, user_id, ranking, metric) FROM stdin;
\.


--
-- Name: user_metric_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('user_metric_pk_seq', 1, false);


--
-- Data for Name: user_plugin; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY user_plugin (user_plugin_id, user_id, plugin_id) FROM stdin;
\.


--
-- Name: user_plugin_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('user_plugin_pk_seq', 1, false);


--
-- Data for Name: user_preferences; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY user_preferences (user_id, preference_name, dead1, set_date, preference_value) FROM stdin;
\.


--
-- Data for Name: user_ratings; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY user_ratings (rated_by, user_id, rate_field, rating) FROM stdin;
\.


--
-- Data for Name: user_session; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY user_session (user_id, session_hash, ip_addr, "time") FROM stdin;
100	867b0c76e3a110d924f98029a28baa95	               	1096480068
\.


--
-- Data for Name: user_type; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY user_type (type_id, type_name) FROM stdin;
1	User
2	UserPool
\.


--
-- Name: user_type_type_id_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('user_type_type_id_seq', 2, true);


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: fusionforge
--

COPY users (user_id, user_name, email, user_pw, realname, status, shell, unix_pw, unix_status, unix_uid, unix_box, add_date, confirm_hash, mail_siteupdates, mail_va, authorized_keys, email_new, people_view_skills, people_resume, timezone, language, block_ratings, jabber_address, jabber_only, address, phone, fax, title, firstname, lastname, address2, ccode, theme_id, type_id, unix_gid) FROM stdin;
2	noreply				D	/bin/bash		N	20002	shell1	0	\N	0	0	\N	\N	0		GMT	1	0	\N	\N	\N	\N	\N	\N		\N	\N	US	1	1	20002
100	None	noreply@sourceforge.net	*********34343	Nobody	D	/bin/bash		N	20100	shell1	0	\N	0	0	\N	\N	0		GMT	1	0	\N	\N	\N	\N	\N	\N	Nobody	\N	\N	US	1	1	20100
\.


--
-- Name: users_pk_seq; Type: SEQUENCE SET; Schema: public; Owner: fusionforge
--

SELECT pg_catalog.setval('users_pk_seq', 101, true);


--
-- Name: artifact_canned_responses_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY artifact_canned_responses
    ADD CONSTRAINT artifact_canned_responses_pkey PRIMARY KEY (id);


--
-- Name: artifact_counts_agg_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY artifact_counts_agg
    ADD CONSTRAINT artifact_counts_agg_pkey PRIMARY KEY (group_artifact_id);


--
-- Name: artifact_extra_field_data_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY artifact_extra_field_data
    ADD CONSTRAINT artifact_extra_field_data_pkey PRIMARY KEY (data_id);


--
-- Name: artifact_extra_field_elements_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY artifact_extra_field_elements
    ADD CONSTRAINT artifact_extra_field_elements_pkey PRIMARY KEY (element_id);


--
-- Name: artifact_extra_field_list_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY artifact_extra_field_list
    ADD CONSTRAINT artifact_extra_field_list_pkey PRIMARY KEY (extra_field_id);


--
-- Name: artifact_extra_field_list_unique; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY artifact_extra_field_list
    ADD CONSTRAINT artifact_extra_field_list_unique UNIQUE (group_artifact_id, extra_field_id);


--
-- Name: artifact_file_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY artifact_file
    ADD CONSTRAINT artifact_file_pkey PRIMARY KEY (id);


--
-- Name: artifact_group_list_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY artifact_group_list
    ADD CONSTRAINT artifact_group_list_pkey PRIMARY KEY (group_artifact_id);


--
-- Name: artifact_history_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY artifact_history
    ADD CONSTRAINT artifact_history_pkey PRIMARY KEY (id);


--
-- Name: artifact_message_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY artifact_message
    ADD CONSTRAINT artifact_message_pkey PRIMARY KEY (id);


--
-- Name: artifact_monitor_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY artifact_monitor
    ADD CONSTRAINT artifact_monitor_pkey PRIMARY KEY (artifact_id, user_id);


--
-- Name: artifact_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY artifact
    ADD CONSTRAINT artifact_pkey PRIMARY KEY (artifact_id);


--
-- Name: artifact_query_elements_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY artifact_query_fields
    ADD CONSTRAINT artifact_query_elements_pkey PRIMARY KEY (artifact_query_id, query_field_type, query_field_id);


--
-- Name: artifact_query_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY artifact_query
    ADD CONSTRAINT artifact_query_pkey PRIMARY KEY (artifact_query_id);


--
-- Name: artifact_status_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY artifact_status
    ADD CONSTRAINT artifact_status_pkey PRIMARY KEY (id);


--
-- Name: artifact_type_monitor_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY artifact_type_monitor
    ADD CONSTRAINT artifact_type_monitor_pkey PRIMARY KEY (group_artifact_id, user_id);


--
-- Name: artifact_workflow_event_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY artifact_workflow_event
    ADD CONSTRAINT artifact_workflow_event_pkey PRIMARY KEY (event_id);


--
-- Name: artifact_workflow_notify_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY artifact_workflow_notify
    ADD CONSTRAINT artifact_workflow_notify_pkey PRIMARY KEY (event_id, role_id);


--
-- Name: artifact_workflow_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY artifact_workflow_roles
    ADD CONSTRAINT artifact_workflow_roles_pkey PRIMARY KEY (event_id, role_id);


--
-- Name: canned_responses_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY canned_responses
    ADD CONSTRAINT canned_responses_pkey PRIMARY KEY (response_id);


--
-- Name: country_code_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY country_code
    ADD CONSTRAINT country_code_pkey PRIMARY KEY (ccode);


--
-- Name: db_images_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY db_images
    ADD CONSTRAINT db_images_pkey PRIMARY KEY (id);


--
-- Name: doc_data_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY doc_data
    ADD CONSTRAINT doc_data_pkey PRIMARY KEY (docid);


--
-- Name: doc_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY doc_groups
    ADD CONSTRAINT doc_groups_pkey PRIMARY KEY (doc_group);


--
-- Name: doc_states_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY doc_states
    ADD CONSTRAINT doc_states_pkey PRIMARY KEY (stateid);


--
-- Name: filemodule_monitor_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY filemodule_monitor
    ADD CONSTRAINT filemodule_monitor_pkey PRIMARY KEY (filemodule_id, user_id);


--
-- Name: form_keys_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY form_keys
    ADD CONSTRAINT form_keys_pkey PRIMARY KEY (key_id);


--
-- Name: forum_agg_msg_count_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY forum_agg_msg_count
    ADD CONSTRAINT forum_agg_msg_count_pkey PRIMARY KEY (group_forum_id);


--
-- Name: forum_attachment_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY forum_attachment
    ADD CONSTRAINT forum_attachment_pkey PRIMARY KEY (attachmentid);


--
-- Name: forum_attachment_type_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY forum_attachment_type
    ADD CONSTRAINT forum_attachment_type_pkey PRIMARY KEY (extension);


--
-- Name: forum_group_list_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY forum_group_list
    ADD CONSTRAINT forum_group_list_pkey PRIMARY KEY (group_forum_id);


--
-- Name: forum_monitored_forums_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY forum_monitored_forums
    ADD CONSTRAINT forum_monitored_forums_pkey PRIMARY KEY (forum_id, user_id);


--
-- Name: forum_pending_attachment_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY forum_pending_attachment
    ADD CONSTRAINT forum_pending_attachment_pkey PRIMARY KEY (attachmentid);


--
-- Name: forum_pending_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY forum_pending_messages
    ADD CONSTRAINT forum_pending_messages_pkey PRIMARY KEY (msg_id);


--
-- Name: forum_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY forum
    ADD CONSTRAINT forum_pkey PRIMARY KEY (msg_id);


--
-- Name: forum_saved_place_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY forum_saved_place
    ADD CONSTRAINT forum_saved_place_pkey PRIMARY KEY (user_id, forum_id);


--
-- Name: frs_dlstats_filetotal_agg_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY frs_dlstats_filetotal_agg
    ADD CONSTRAINT frs_dlstats_filetotal_agg_pkey PRIMARY KEY (file_id);


--
-- Name: frs_file_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY frs_file
    ADD CONSTRAINT frs_file_pkey PRIMARY KEY (file_id);


--
-- Name: frs_filetype_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY frs_filetype
    ADD CONSTRAINT frs_filetype_pkey PRIMARY KEY (type_id);


--
-- Name: frs_package_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY frs_package
    ADD CONSTRAINT frs_package_pkey PRIMARY KEY (package_id);


--
-- Name: frs_processor_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY frs_processor
    ADD CONSTRAINT frs_processor_pkey PRIMARY KEY (processor_id);


--
-- Name: frs_release_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY frs_release
    ADD CONSTRAINT frs_release_pkey PRIMARY KEY (release_id);


--
-- Name: frs_status_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY frs_status
    ADD CONSTRAINT frs_status_pkey PRIMARY KEY (status_id);


--
-- Name: group_activity_monitor_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY group_activity_monitor
    ADD CONSTRAINT group_activity_monitor_pkey PRIMARY KEY (group_id, user_id);


--
-- Name: group_history_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY group_history
    ADD CONSTRAINT group_history_pkey PRIMARY KEY (group_history_id);


--
-- Name: group_join_request_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY group_join_request
    ADD CONSTRAINT group_join_request_pkey PRIMARY KEY (group_id, user_id);


--
-- Name: group_plugin_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY group_plugin
    ADD CONSTRAINT group_plugin_pkey PRIMARY KEY (group_plugin_id);


--
-- Name: groups_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY groups
    ADD CONSTRAINT groups_pkey PRIMARY KEY (group_id);


--
-- Name: key; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY form_keys
    ADD CONSTRAINT key UNIQUE (key);


--
-- Name: licenses_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY licenses
    ADD CONSTRAINT licenses_pkey PRIMARY KEY (license_id);


--
-- Name: mail_group_list_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY mail_group_list
    ADD CONSTRAINT mail_group_list_pkey PRIMARY KEY (group_list_id);


--
-- Name: massmail_queue_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY massmail_queue
    ADD CONSTRAINT massmail_queue_pkey PRIMARY KEY (id);


--
-- Name: news_bytes_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY news_bytes
    ADD CONSTRAINT news_bytes_pkey PRIMARY KEY (id);


--
-- Name: people_job_category_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY people_job_category
    ADD CONSTRAINT people_job_category_pkey PRIMARY KEY (category_id);


--
-- Name: people_job_inventory_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY people_job_inventory
    ADD CONSTRAINT people_job_inventory_pkey PRIMARY KEY (job_inventory_id);


--
-- Name: people_job_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY people_job
    ADD CONSTRAINT people_job_pkey PRIMARY KEY (job_id);


--
-- Name: people_job_status_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY people_job_status
    ADD CONSTRAINT people_job_status_pkey PRIMARY KEY (status_id);


--
-- Name: people_skill_inventory_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY people_skill_inventory
    ADD CONSTRAINT people_skill_inventory_pkey PRIMARY KEY (skill_inventory_id);


--
-- Name: people_skill_level_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY people_skill_level
    ADD CONSTRAINT people_skill_level_pkey PRIMARY KEY (skill_level_id);


--
-- Name: people_skill_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY people_skill
    ADD CONSTRAINT people_skill_pkey PRIMARY KEY (skill_id);


--
-- Name: people_skill_year_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY people_skill_year
    ADD CONSTRAINT people_skill_year_pkey PRIMARY KEY (skill_year_id);


--
-- Name: plugin_cvstracker_artifact_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY plugin_cvstracker_data_artifact
    ADD CONSTRAINT plugin_cvstracker_artifact_pkey PRIMARY KEY (id);


--
-- Name: plugin_cvstracker_master_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY plugin_cvstracker_data_master
    ADD CONSTRAINT plugin_cvstracker_master_pkey PRIMARY KEY (id);


--
-- Name: plugins_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY plugins
    ADD CONSTRAINT plugins_pkey PRIMARY KEY (plugin_id);


--
-- Name: prdb_dbs_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY prdb_dbs
    ADD CONSTRAINT prdb_dbs_pkey PRIMARY KEY (dbid);


--
-- Name: prdb_types_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY prdb_types
    ADD CONSTRAINT prdb_types_pkey PRIMARY KEY (dbtypeid);


--
-- Name: project_assigned_to_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY project_assigned_to
    ADD CONSTRAINT project_assigned_to_pkey PRIMARY KEY (project_task_id, assigned_to_id);


--
-- Name: project_counts_agg_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY project_counts_agg
    ADD CONSTRAINT project_counts_agg_pkey PRIMARY KEY (group_project_id);


--
-- Name: project_dependencies_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY project_dependencies
    ADD CONSTRAINT project_dependencies_pkey PRIMARY KEY (project_task_id, is_dependent_on_task_id);


--
-- Name: project_group_list_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY project_group_list
    ADD CONSTRAINT project_group_list_pkey PRIMARY KEY (group_project_id);


--
-- Name: project_history_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY project_history
    ADD CONSTRAINT project_history_pkey PRIMARY KEY (project_history_id);


--
-- Name: project_metric_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY project_metric
    ADD CONSTRAINT project_metric_pkey PRIMARY KEY (ranking);


--
-- Name: project_metric_tmp1_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY project_metric_tmp1
    ADD CONSTRAINT project_metric_tmp1_pkey PRIMARY KEY (ranking);


--
-- Name: project_status_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY project_status
    ADD CONSTRAINT project_status_pkey PRIMARY KEY (status_id);


--
-- Name: project_sums_agg_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY project_sums_agg
    ADD CONSTRAINT project_sums_agg_pkey PRIMARY KEY (group_id, type);


--
-- Name: project_task_artifact_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY project_task_artifact
    ADD CONSTRAINT project_task_artifact_pkey PRIMARY KEY (project_task_id, artifact_id);


--
-- Name: project_task_external_order_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY project_task_external_order
    ADD CONSTRAINT project_task_external_order_pkey PRIMARY KEY (project_task_id);


--
-- Name: project_task_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY project_task
    ADD CONSTRAINT project_task_pkey PRIMARY KEY (project_task_id);


--
-- Name: prweb_vhost_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY prweb_vhost
    ADD CONSTRAINT prweb_vhost_pkey PRIMARY KEY (vhostid);


--
-- Name: rep_group_act_daily_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY rep_group_act_daily
    ADD CONSTRAINT rep_group_act_daily_pkey PRIMARY KEY (group_id, day);


--
-- Name: rep_group_act_monthly_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY rep_group_act_monthly
    ADD CONSTRAINT rep_group_act_monthly_pkey PRIMARY KEY (group_id, month);


--
-- Name: rep_group_act_weekly_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY rep_group_act_weekly
    ADD CONSTRAINT rep_group_act_weekly_pkey PRIMARY KEY (group_id, week);


--
-- Name: rep_groups_added_daily_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY rep_groups_added_daily
    ADD CONSTRAINT rep_groups_added_daily_pkey PRIMARY KEY (day);


--
-- Name: rep_groups_added_monthly_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY rep_groups_added_monthly
    ADD CONSTRAINT rep_groups_added_monthly_pkey PRIMARY KEY (month);


--
-- Name: rep_groups_added_weekly_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY rep_groups_added_weekly
    ADD CONSTRAINT rep_groups_added_weekly_pkey PRIMARY KEY (week);


--
-- Name: rep_groups_cum_daily_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY rep_groups_cum_daily
    ADD CONSTRAINT rep_groups_cum_daily_pkey PRIMARY KEY (day);


--
-- Name: rep_groups_cum_monthly_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY rep_groups_cum_monthly
    ADD CONSTRAINT rep_groups_cum_monthly_pkey PRIMARY KEY (month);


--
-- Name: rep_groups_cum_weekly_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY rep_groups_cum_weekly
    ADD CONSTRAINT rep_groups_cum_weekly_pkey PRIMARY KEY (week);


--
-- Name: rep_time_category_time_code_key; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY rep_time_category
    ADD CONSTRAINT rep_time_category_time_code_key UNIQUE (time_code);


--
-- Name: rep_user_act_daily_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY rep_user_act_daily
    ADD CONSTRAINT rep_user_act_daily_pkey PRIMARY KEY (user_id, day);


--
-- Name: rep_user_act_monthly_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY rep_user_act_monthly
    ADD CONSTRAINT rep_user_act_monthly_pkey PRIMARY KEY (user_id, month);


--
-- Name: rep_user_act_weekly_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY rep_user_act_weekly
    ADD CONSTRAINT rep_user_act_weekly_pkey PRIMARY KEY (user_id, week);


--
-- Name: rep_users_added_daily_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY rep_users_added_daily
    ADD CONSTRAINT rep_users_added_daily_pkey PRIMARY KEY (day);


--
-- Name: rep_users_added_monthly_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY rep_users_added_monthly
    ADD CONSTRAINT rep_users_added_monthly_pkey PRIMARY KEY (month);


--
-- Name: rep_users_added_weekly_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY rep_users_added_weekly
    ADD CONSTRAINT rep_users_added_weekly_pkey PRIMARY KEY (week);


--
-- Name: rep_users_cum_daily_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY rep_users_cum_daily
    ADD CONSTRAINT rep_users_cum_daily_pkey PRIMARY KEY (day);


--
-- Name: rep_users_cum_monthly_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY rep_users_cum_monthly
    ADD CONSTRAINT rep_users_cum_monthly_pkey PRIMARY KEY (month);


--
-- Name: rep_users_cum_weekly_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY rep_users_cum_weekly
    ADD CONSTRAINT rep_users_cum_weekly_pkey PRIMARY KEY (week);


--
-- Name: role_role_id_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY role
    ADD CONSTRAINT role_role_id_pkey PRIMARY KEY (role_id);


--
-- Name: role_setting_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY role_setting
    ADD CONSTRAINT role_setting_pkey PRIMARY KEY (role_id, section_name, ref_id);


--
-- Name: session_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY user_session
    ADD CONSTRAINT session_pkey PRIMARY KEY (session_hash);


--
-- Name: skills_data_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY skills_data
    ADD CONSTRAINT skills_data_pkey PRIMARY KEY (skills_data_id);


--
-- Name: skills_data_types_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY skills_data_types
    ADD CONSTRAINT skills_data_types_pkey PRIMARY KEY (type_id);


--
-- Name: snippet_package_item_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY snippet_package_item
    ADD CONSTRAINT snippet_package_item_pkey PRIMARY KEY (snippet_package_item_id);


--
-- Name: snippet_package_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY snippet_package
    ADD CONSTRAINT snippet_package_pkey PRIMARY KEY (snippet_package_id);


--
-- Name: snippet_package_version_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY snippet_package_version
    ADD CONSTRAINT snippet_package_version_pkey PRIMARY KEY (snippet_package_version_id);


--
-- Name: snippet_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY snippet
    ADD CONSTRAINT snippet_pkey PRIMARY KEY (snippet_id);


--
-- Name: snippet_version_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY snippet_version
    ADD CONSTRAINT snippet_version_pkey PRIMARY KEY (snippet_version_id);


--
-- Name: supported_languages_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY supported_languages
    ADD CONSTRAINT supported_languages_pkey PRIMARY KEY (language_id);


--
-- Name: survey_question_types_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY survey_question_types
    ADD CONSTRAINT survey_question_types_pkey PRIMARY KEY (id);


--
-- Name: survey_questions_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY survey_questions
    ADD CONSTRAINT survey_questions_pkey PRIMARY KEY (question_id);


--
-- Name: surveys_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY surveys
    ADD CONSTRAINT surveys_pkey PRIMARY KEY (survey_id);


--
-- Name: trove_cat_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY trove_cat
    ADD CONSTRAINT trove_cat_pkey PRIMARY KEY (trove_cat_id);


--
-- Name: trove_group_link_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY trove_group_link
    ADD CONSTRAINT trove_group_link_pkey PRIMARY KEY (trove_cat_id, group_id, trove_cat_version);


--
-- Name: trove_treesums_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY trove_treesums
    ADD CONSTRAINT trove_treesums_pkey PRIMARY KEY (trove_cat_id);


--
-- Name: user_bookmarks_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY user_bookmarks
    ADD CONSTRAINT user_bookmarks_pkey PRIMARY KEY (bookmark_id);


--
-- Name: user_diary_monitor_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY user_diary_monitor
    ADD CONSTRAINT user_diary_monitor_pkey PRIMARY KEY (monitored_user, user_id);


--
-- Name: user_diary_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY user_diary
    ADD CONSTRAINT user_diary_pkey PRIMARY KEY (id);


--
-- Name: user_group_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY user_group
    ADD CONSTRAINT user_group_pkey PRIMARY KEY (group_id, user_id);


--
-- Name: user_metric0_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY user_metric0
    ADD CONSTRAINT user_metric0_pkey PRIMARY KEY (ranking);


--
-- Name: user_metric_history_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY user_metric_history
    ADD CONSTRAINT user_metric_history_pkey PRIMARY KEY (month, day, user_id);


--
-- Name: user_metric_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY user_metric
    ADD CONSTRAINT user_metric_pkey PRIMARY KEY (ranking);


--
-- Name: user_plugin_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY user_plugin
    ADD CONSTRAINT user_plugin_pkey PRIMARY KEY (user_id, plugin_id);


--
-- Name: user_preferences_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY user_preferences
    ADD CONSTRAINT user_preferences_pkey PRIMARY KEY (user_id, preference_name);


--
-- Name: user_ratings_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY user_ratings
    ADD CONSTRAINT user_ratings_pkey PRIMARY KEY (rated_by, user_id, rate_field);


--
-- Name: user_type_type_id_key; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY user_type
    ADD CONSTRAINT user_type_type_id_key UNIQUE (type_id);


--
-- Name: users_pkey; Type: CONSTRAINT; Schema: public; Owner: fusionforge; Tablespace: 
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_pkey PRIMARY KEY (user_id);


--
-- Name: art_assign_status; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX art_assign_status ON artifact USING btree (assigned_to, status_id);


--
-- Name: art_groupartid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX art_groupartid ON artifact USING btree (group_artifact_id);


--
-- Name: art_groupartid_artifactid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX art_groupartid_artifactid ON artifact USING btree (group_artifact_id, artifact_id);


--
-- Name: art_groupartid_assign; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX art_groupartid_assign ON artifact USING btree (group_artifact_id, assigned_to);


--
-- Name: art_groupartid_statusid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX art_groupartid_statusid ON artifact USING btree (group_artifact_id, status_id);


--
-- Name: art_groupartid_submit; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX art_groupartid_submit ON artifact USING btree (group_artifact_id, submitted_by);


--
-- Name: art_submit_status; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX art_submit_status ON artifact USING btree (submitted_by, status_id);


--
-- Name: artfile_artid_adddate; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX artfile_artid_adddate ON artifact_file USING btree (artifact_id, adddate);


--
-- Name: artgrouplist_groupid_public; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX artgrouplist_groupid_public ON artifact_group_list USING btree (group_id, is_public);


--
-- Name: arthistory_artid_entrydate; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX arthistory_artid_entrydate ON artifact_history USING btree (artifact_id, entrydate);


--
-- Name: artifact_workflow_event_index; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX artifact_workflow_event_index ON artifact_workflow_event USING btree (event_id, group_artifact_id, field_id);


--
-- Name: artifactcannedresponses_groupid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX artifactcannedresponses_groupid ON artifact_canned_responses USING btree (group_artifact_id);


--
-- Name: artifactextrafielddata_artifactid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX artifactextrafielddata_artifactid ON artifact_extra_field_data USING btree (artifact_id);


--
-- Name: artifactextrafieldlist_groupartid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX artifactextrafieldlist_groupartid ON artifact_extra_field_list USING btree (group_artifact_id);


--
-- Name: artifactextrafldlmts_extrafieldid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX artifactextrafldlmts_extrafieldid ON artifact_extra_field_elements USING btree (extra_field_id);


--
-- Name: artmessage_artid_adddate; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX artmessage_artid_adddate ON artifact_message USING btree (artifact_id, adddate);


--
-- Name: artmonitor_useridartid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX artmonitor_useridartid ON artifact_monitor USING btree (user_id, artifact_id);


--
-- Name: cronhist_jobrundate; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX cronhist_jobrundate ON cron_history USING btree (job, rundate);


--
-- Name: cronhist_rundate; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX cronhist_rundate ON cron_history USING btree (rundate);


--
-- Name: db_images_group; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX db_images_group ON db_images USING btree (group_id);


--
-- Name: doc_groups_group; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX doc_groups_group ON doc_groups USING btree (group_id);


--
-- Name: docdata_groupid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX docdata_groupid ON doc_data USING btree (group_id, doc_group);


--
-- Name: docgroups_parentdocgroup; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX docgroups_parentdocgroup ON doc_groups USING btree (parent_doc_group);


--
-- Name: filemodulemonitor_useridfilemoduleid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX filemodulemonitor_useridfilemoduleid ON filemodule_monitor USING btree (user_id, filemodule_id);


--
-- Name: forum_forumid_isfollto_mostrece; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX forum_forumid_isfollto_mostrece ON forum USING btree (group_forum_id, is_followup_to, most_recent_date);


--
-- Name: forum_forumid_msgid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX forum_forumid_msgid ON forum USING btree (group_forum_id, msg_id);


--
-- Name: forum_forumid_threadid_mostrece; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX forum_forumid_threadid_mostrece ON forum USING btree (group_forum_id, thread_id, most_recent_date);


--
-- Name: forum_group_forum_id; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX forum_group_forum_id ON forum USING btree (group_forum_id);


--
-- Name: forum_group_list_group_id; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX forum_group_list_group_id ON forum_group_list USING btree (group_id);


--
-- Name: forum_threadid_isfollowupto; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX forum_threadid_isfollowupto ON forum USING btree (thread_id, is_followup_to);


--
-- Name: forummonitoredforums_useridforumid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX forummonitoredforums_useridforumid ON forum_monitored_forums USING btree (user_id, forum_id);


--
-- Name: frs_file_date; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX frs_file_date ON frs_file USING btree (post_date);


--
-- Name: frs_file_release_id; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX frs_file_release_id ON frs_file USING btree (release_id);


--
-- Name: frs_release_package; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX frs_release_package ON frs_release USING btree (package_id);


--
-- Name: group_history_group_id; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX group_history_group_id ON group_history USING btree (group_id);


--
-- Name: group_unix_uniq; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE UNIQUE INDEX group_unix_uniq ON groups USING btree (unix_group_name);


--
-- Name: groupcvshistory_groupid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX groupcvshistory_groupid ON group_cvs_history USING btree (group_id);


--
-- Name: groupplugin_groupid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX groupplugin_groupid ON group_plugin USING btree (group_id);


--
-- Name: groups_public; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX groups_public ON groups USING btree (is_public);


--
-- Name: groups_status; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX groups_status ON groups USING btree (status);


--
-- Name: groups_type; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX groups_type ON groups USING btree (type_id);


--
-- Name: idx_prdb_dbname; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE UNIQUE INDEX idx_prdb_dbname ON prdb_dbs USING btree (dbname);


--
-- Name: idx_vhost_groups; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX idx_vhost_groups ON prweb_vhost USING btree (group_id);


--
-- Name: idx_vhost_hostnames; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE UNIQUE INDEX idx_vhost_hostnames ON prweb_vhost USING btree (vhost_name);


--
-- Name: mail_group_list_group; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX mail_group_list_group ON mail_group_list USING btree (group_id);


--
-- Name: news_approved_date; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX news_approved_date ON news_bytes USING btree (is_approved, post_date);


--
-- Name: news_bytes_approved; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX news_bytes_approved ON news_bytes USING btree (is_approved);


--
-- Name: news_bytes_forum; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX news_bytes_forum ON news_bytes USING btree (forum_id);


--
-- Name: news_bytes_group; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX news_bytes_group ON news_bytes USING btree (group_id);


--
-- Name: news_group_date; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX news_group_date ON news_bytes USING btree (group_id, post_date);


--
-- Name: package_group_id; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX package_group_id ON frs_package USING btree (group_id);


--
-- Name: pages_by_day_day; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX pages_by_day_day ON stats_agg_pages_by_day USING btree (day);


--
-- Name: people_job_group_id; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX people_job_group_id ON people_job USING btree (group_id);


--
-- Name: plugin_cvstracker_group_artifact_id; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX plugin_cvstracker_group_artifact_id ON plugin_cvstracker_data_artifact USING btree (group_artifact_id);


--
-- Name: plugins_plugin_name_key; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE UNIQUE INDEX plugins_plugin_name_key ON plugins USING btree (plugin_name);


--
-- Name: prdbdbs_groupid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX prdbdbs_groupid ON prdb_dbs USING btree (group_id);


--
-- Name: prdbstates_stateid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX prdbstates_stateid ON prdb_states USING btree (stateid);


--
-- Name: project_categor_category_id_key; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE UNIQUE INDEX project_categor_category_id_key ON project_category USING btree (category_id);


--
-- Name: project_group_list_group_id; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX project_group_list_group_id ON project_group_list USING btree (group_id);


--
-- Name: project_history_task_id; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX project_history_task_id ON project_history USING btree (project_task_id);


--
-- Name: project_messa_project_messa_key; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE UNIQUE INDEX project_messa_project_messa_key ON project_messages USING btree (project_message_id);


--
-- Name: project_metric_group; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX project_metric_group ON project_metric USING btree (group_id);


--
-- Name: project_metric_weekly_group; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX project_metric_weekly_group ON project_weekly_metric USING btree (group_id);


--
-- Name: projectassigned_assignedtotaskid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX projectassigned_assignedtotaskid ON project_assigned_to USING btree (assigned_to_id, project_task_id);


--
-- Name: projectcategory_groupprojectid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX projectcategory_groupprojectid ON project_category USING btree (group_project_id);


--
-- Name: projectdep_isdepon_projtaskid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX projectdep_isdepon_projtaskid ON project_dependencies USING btree (is_dependent_on_task_id, project_task_id);


--
-- Name: projectmsgs_projtaskidpostdate; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX projectmsgs_projtaskidpostdate ON project_messages USING btree (project_task_id, postdate);


--
-- Name: projecttask_projid_status; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX projecttask_projid_status ON project_task USING btree (group_project_id, status_id);


--
-- Name: projecttaskartifact_artidprojtaskid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX projecttaskartifact_artidprojtaskid ON project_task_artifact USING btree (artifact_id, project_task_id);


--
-- Name: projectweeklymetric_ranking; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX projectweeklymetric_ranking ON project_weekly_metric USING btree (ranking);


--
-- Name: repgroupactdaily_day; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX repgroupactdaily_day ON rep_group_act_daily USING btree (day);


--
-- Name: repgroupactmonthly_month; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX repgroupactmonthly_month ON rep_group_act_monthly USING btree (month);


--
-- Name: repgroupactweekly_week; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX repgroupactweekly_week ON rep_group_act_weekly USING btree (week);


--
-- Name: reptimetracking_userdate; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX reptimetracking_userdate ON rep_time_tracking USING btree (user_id, week);


--
-- Name: role_groupidroleid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE UNIQUE INDEX role_groupidroleid ON role USING btree (group_id, role_id);


--
-- Name: session_time; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX session_time ON user_session USING btree ("time");


--
-- Name: session_user_id; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX session_user_id ON user_session USING btree (user_id);


--
-- Name: snippet_category; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX snippet_category ON snippet USING btree (category);


--
-- Name: snippet_language; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX snippet_language ON snippet USING btree (language);


--
-- Name: snippet_package_category; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX snippet_package_category ON snippet_package USING btree (category);


--
-- Name: snippet_package_item_pkg_ver; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX snippet_package_item_pkg_ver ON snippet_package_item USING btree (snippet_package_version_id);


--
-- Name: snippet_package_language; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX snippet_package_language ON snippet_package USING btree (language);


--
-- Name: snippet_package_version_pkg_id; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX snippet_package_version_pkg_id ON snippet_package_version USING btree (snippet_package_id);


--
-- Name: snippet_version_snippet_id; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX snippet_version_snippet_id ON snippet_version USING btree (snippet_id);


--
-- Name: statscvsgroup_month_day_group; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE UNIQUE INDEX statscvsgroup_month_day_group ON stats_cvs_group USING btree (month, day, group_id);


--
-- Name: statslogobygroup_month_day_grou; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE UNIQUE INDEX statslogobygroup_month_day_grou ON stats_agg_logo_by_group USING btree (month, day, group_id);


--
-- Name: statsproject_month_day_group; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE UNIQUE INDEX statsproject_month_day_group ON stats_project USING btree (month, day, group_id);


--
-- Name: statsprojectdev_month_day_group; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE UNIQUE INDEX statsprojectdev_month_day_group ON stats_project_developers USING btree (month, day, group_id);


--
-- Name: statsprojectmetric_month_day_gr; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE UNIQUE INDEX statsprojectmetric_month_day_gr ON stats_project_metric USING btree (month, day, group_id);


--
-- Name: statsprojectmonths_groupid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX statsprojectmonths_groupid ON stats_project_months USING btree (group_id);


--
-- Name: statsprojectmonths_groupid_mont; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX statsprojectmonths_groupid_mont ON stats_project_months USING btree (group_id, month);


--
-- Name: statssite_month_day; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE UNIQUE INDEX statssite_month_day ON stats_site USING btree (month, day);


--
-- Name: statssitebygroup_month_day_grou; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE UNIQUE INDEX statssitebygroup_month_day_grou ON stats_agg_site_by_group USING btree (month, day, group_id);


--
-- Name: statssitemonths_month; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX statssitemonths_month ON stats_site_months USING btree (month);


--
-- Name: statssitepagesbyday_month_day; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX statssitepagesbyday_month_day ON stats_site_pages_by_day USING btree (month, day);


--
-- Name: statssubdpages_month_day_group; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE UNIQUE INDEX statssubdpages_month_day_group ON stats_subd_pages USING btree (month, day, group_id);


--
-- Name: supportedlanguage_code; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE UNIQUE INDEX supportedlanguage_code ON supported_languages USING btree (language_code);


--
-- Name: survey_questions_group; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX survey_questions_group ON survey_questions USING btree (group_id);


--
-- Name: survey_rating_aggregate_type_id; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX survey_rating_aggregate_type_id ON survey_rating_aggregate USING btree (type, id);


--
-- Name: survey_rating_responses_type_id; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX survey_rating_responses_type_id ON survey_rating_response USING btree (type, id);


--
-- Name: survey_rating_responses_user_ty; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX survey_rating_responses_user_ty ON survey_rating_response USING btree (user_id, type, id);


--
-- Name: survey_responses_group_id; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX survey_responses_group_id ON survey_responses USING btree (group_id);


--
-- Name: survey_responses_survey_questio; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX survey_responses_survey_questio ON survey_responses USING btree (survey_id, question_id);


--
-- Name: survey_responses_user_survey_qu; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX survey_responses_user_survey_qu ON survey_responses USING btree (user_id, survey_id, question_id);


--
-- Name: surveys_group; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX surveys_group ON surveys USING btree (group_id);


--
-- Name: themes_theme_id_key; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE UNIQUE INDEX themes_theme_id_key ON themes USING btree (theme_id);


--
-- Name: troveagg_trovecatid_ranking; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX troveagg_trovecatid_ranking ON trove_agg USING btree (trove_cat_id, ranking);


--
-- Name: trovecat_parentid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX trovecat_parentid ON trove_cat USING btree (parent);


--
-- Name: trovegrouplink_groupidcatid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX trovegrouplink_groupidcatid ON trove_group_link USING btree (group_id, trove_cat_id);


--
-- Name: user_bookmark_user_id; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX user_bookmark_user_id ON user_bookmarks USING btree (user_id);


--
-- Name: user_diary_user_date; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX user_diary_user_date ON user_diary USING btree (user_id, date_posted);


--
-- Name: user_metric0_user_id; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX user_metric0_user_id ON user_metric0 USING btree (user_id);


--
-- Name: user_ratings_user_id; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX user_ratings_user_id ON user_ratings USING btree (user_id);


--
-- Name: userdiarymon_useridmonitoredid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX userdiarymon_useridmonitoredid ON user_diary_monitor USING btree (user_id, monitored_user);


--
-- Name: usergroup_useridgroupid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX usergroup_useridgroupid ON user_group USING btree (user_id, group_id);


--
-- Name: usermetric_userid; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE UNIQUE INDEX usermetric_userid ON user_metric USING btree (user_id);


--
-- Name: usermetrichistory_useridmonthday; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX usermetrichistory_useridmonthday ON user_metric_history USING btree (user_id, month, day);


--
-- Name: users_namename_uniq; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE UNIQUE INDEX users_namename_uniq ON users USING btree (user_name);


--
-- Name: users_status; Type: INDEX; Schema: public; Owner: fusionforge; Tablespace: 
--

CREATE INDEX users_status ON users USING btree (status);


--
-- Name: artifact_delete_agg; Type: RULE; Schema: public; Owner: fusionforge
--

CREATE RULE artifact_delete_agg AS ON DELETE TO artifact DO UPDATE artifact_counts_agg SET count = (artifact_counts_agg.count - 1), open_count = CASE WHEN (old.status_id = 1) THEN (artifact_counts_agg.open_count - 1) ELSE artifact_counts_agg.open_count END WHERE (artifact_counts_agg.group_artifact_id = old.group_artifact_id);


--
-- Name: artifact_insert_agg; Type: RULE; Schema: public; Owner: fusionforge
--

CREATE RULE artifact_insert_agg AS ON INSERT TO artifact DO UPDATE artifact_counts_agg SET count = (artifact_counts_agg.count + 1), open_count = (artifact_counts_agg.open_count + 1) WHERE (artifact_counts_agg.group_artifact_id = new.group_artifact_id);


--
-- Name: forum_delete_agg; Type: RULE; Schema: public; Owner: fusionforge
--

CREATE RULE forum_delete_agg AS ON DELETE TO forum DO UPDATE forum_agg_msg_count SET count = (forum_agg_msg_count.count - 1) WHERE (forum_agg_msg_count.group_forum_id = old.group_forum_id);


--
-- Name: forum_insert_agg; Type: RULE; Schema: public; Owner: fusionforge
--

CREATE RULE forum_insert_agg AS ON INSERT TO forum DO UPDATE forum_agg_msg_count SET count = (forum_agg_msg_count.count + 1) WHERE (forum_agg_msg_count.group_forum_id = new.group_forum_id);


--
-- Name: frs_dlstats_file_rule; Type: RULE; Schema: public; Owner: fusionforge
--

CREATE RULE frs_dlstats_file_rule AS ON INSERT TO frs_dlstats_file DO UPDATE frs_dlstats_filetotal_agg SET downloads = (frs_dlstats_filetotal_agg.downloads + 1) WHERE (frs_dlstats_filetotal_agg.file_id = new.file_id);


--
-- Name: groupactivity_userdelete_rule; Type: RULE; Schema: public; Owner: fusionforge
--

CREATE RULE groupactivity_userdelete_rule AS ON UPDATE TO users DO DELETE FROM group_activity_monitor WHERE (group_activity_monitor.user_id = CASE WHEN (new.status = 'D'::bpchar) THEN new.user_id ELSE 0 END);


--
-- Name: projecttask_delete_agg; Type: RULE; Schema: public; Owner: fusionforge
--

CREATE RULE projecttask_delete_agg AS ON DELETE TO project_task DO UPDATE project_counts_agg SET count = (project_counts_agg.count - 1), open_count = CASE WHEN (old.status_id = 1) THEN (project_counts_agg.open_count - 1) ELSE project_counts_agg.open_count END WHERE (project_counts_agg.group_project_id = old.group_project_id);


--
-- Name: projecttask_insert_agg; Type: RULE; Schema: public; Owner: fusionforge
--

CREATE RULE projecttask_insert_agg AS ON INSERT TO project_task DO UPDATE project_counts_agg SET count = (project_counts_agg.count + 1), open_count = (project_counts_agg.open_count + 1) WHERE (project_counts_agg.group_project_id = new.group_project_id);


--
-- Name: artifact_update_last_modified_date; Type: TRIGGER; Schema: public; Owner: fusionforge
--

CREATE TRIGGER artifact_update_last_modified_date BEFORE INSERT OR UPDATE ON artifact FOR EACH ROW EXECUTE PROCEDURE update_last_modified_date();


--
-- Name: artifactgroup_update_trig; Type: TRIGGER; Schema: public; Owner: fusionforge
--

CREATE TRIGGER artifactgroup_update_trig AFTER UPDATE ON artifact FOR EACH ROW EXECUTE PROCEDURE artifactgroup_update_agg();


--
-- Name: artifactgrouplist_insert_trig; Type: TRIGGER; Schema: public; Owner: fusionforge
--

CREATE TRIGGER artifactgrouplist_insert_trig AFTER INSERT ON artifact_group_list FOR EACH ROW EXECUTE PROCEDURE artifactgrouplist_insert_agg();


--
-- Name: fmsg_agg_trig; Type: TRIGGER; Schema: public; Owner: fusionforge
--

CREATE TRIGGER fmsg_agg_trig AFTER INSERT OR DELETE OR UPDATE ON forum FOR EACH ROW EXECUTE PROCEDURE project_sums('fmsg');


--
-- Name: fora_agg_trig; Type: TRIGGER; Schema: public; Owner: fusionforge
--

CREATE TRIGGER fora_agg_trig AFTER INSERT OR DELETE OR UPDATE ON forum_group_list FOR EACH ROW EXECUTE PROCEDURE project_sums('fora');


--
-- Name: forumgrouplist_insert_trig; Type: TRIGGER; Schema: public; Owner: fusionforge
--

CREATE TRIGGER forumgrouplist_insert_trig AFTER INSERT ON forum_group_list FOR EACH ROW EXECUTE PROCEDURE forumgrouplist_insert_agg();


--
-- Name: frs_file_insert_trig; Type: TRIGGER; Schema: public; Owner: fusionforge
--

CREATE TRIGGER frs_file_insert_trig AFTER INSERT ON frs_file FOR EACH ROW EXECUTE PROCEDURE frs_dlstats_filetotal_insert_ag();


--
-- Name: mail_agg_trig; Type: TRIGGER; Schema: public; Owner: fusionforge
--

CREATE TRIGGER mail_agg_trig AFTER INSERT OR DELETE OR UPDATE ON mail_group_list FOR EACH ROW EXECUTE PROCEDURE project_sums('mail');


--
-- Name: project_task_update_last_modified_date; Type: TRIGGER; Schema: public; Owner: fusionforge
--

CREATE TRIGGER project_task_update_last_modified_date BEFORE INSERT OR UPDATE ON project_task FOR EACH ROW EXECUTE PROCEDURE update_last_modified_date();


--
-- Name: projectgroup_update_trig; Type: TRIGGER; Schema: public; Owner: fusionforge
--

CREATE TRIGGER projectgroup_update_trig AFTER UPDATE ON project_task FOR EACH ROW EXECUTE PROCEDURE projectgroup_update_agg();


--
-- Name: projectgrouplist_insert_trig; Type: TRIGGER; Schema: public; Owner: fusionforge
--

CREATE TRIGGER projectgrouplist_insert_trig AFTER INSERT ON project_group_list FOR EACH ROW EXECUTE PROCEDURE projectgrouplist_insert_agg();


--
-- Name: surveys_agg_trig; Type: TRIGGER; Schema: public; Owner: fusionforge
--

CREATE TRIGGER surveys_agg_trig AFTER INSERT OR DELETE OR UPDATE ON surveys FOR EACH ROW EXECUTE PROCEDURE project_sums('surv');


--
-- Name: $1; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY role
    ADD CONSTRAINT "$1" FOREIGN KEY (group_id) REFERENCES groups(group_id) ON DELETE CASCADE;


--
-- Name: $1; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY project_task_external_order
    ADD CONSTRAINT "$1" FOREIGN KEY (project_task_id) REFERENCES project_task(project_task_id) MATCH FULL ON DELETE CASCADE;


--
-- Name: $1; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY group_join_request
    ADD CONSTRAINT "$1" FOREIGN KEY (group_id) REFERENCES groups(group_id) ON DELETE CASCADE;


--
-- Name: $1; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY artifact_type_monitor
    ADD CONSTRAINT "$1" FOREIGN KEY (group_artifact_id) REFERENCES artifact_group_list(group_artifact_id) ON DELETE CASCADE;


--
-- Name: $1; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY plugin_cvstracker_data_master
    ADD CONSTRAINT "$1" FOREIGN KEY (holder_id) REFERENCES plugin_cvstracker_data_artifact(id);


--
-- Name: $1; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY forum_attachment
    ADD CONSTRAINT "$1" FOREIGN KEY (msg_id) REFERENCES forum(msg_id) ON DELETE CASCADE;


--
-- Name: $2; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY group_join_request
    ADD CONSTRAINT "$2" FOREIGN KEY (user_id) REFERENCES users(user_id);


--
-- Name: $2; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY artifact_type_monitor
    ADD CONSTRAINT "$2" FOREIGN KEY (user_id) REFERENCES users(user_id);


--
-- Name: $2; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY plugin_cvstracker_data_master
    ADD CONSTRAINT "$2" FOREIGN KEY (author) REFERENCES users(user_name);


--
-- Name: $2; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY forum_attachment
    ADD CONSTRAINT "$2" FOREIGN KEY (userid) REFERENCES users(user_id) ON DELETE SET DEFAULT;


--
-- Name: artifact_assignedto_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY artifact
    ADD CONSTRAINT artifact_assignedto_fk FOREIGN KEY (assigned_to) REFERENCES users(user_id) MATCH FULL;


--
-- Name: artifact_groupartifactid_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY artifact
    ADD CONSTRAINT artifact_groupartifactid_fk FOREIGN KEY (group_artifact_id) REFERENCES artifact_group_list(group_artifact_id) MATCH FULL;


--
-- Name: artifact_statusid_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY artifact
    ADD CONSTRAINT artifact_statusid_fk FOREIGN KEY (status_id) REFERENCES artifact_status(id) MATCH FULL;


--
-- Name: artifact_submittedby_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY artifact
    ADD CONSTRAINT artifact_submittedby_fk FOREIGN KEY (submitted_by) REFERENCES users(user_id) MATCH FULL;


--
-- Name: artifact_workflow_event_group_artifact_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY artifact_workflow_event
    ADD CONSTRAINT artifact_workflow_event_group_artifact_id_fkey FOREIGN KEY (group_artifact_id, field_id) REFERENCES artifact_extra_field_list(group_artifact_id, extra_field_id) ON DELETE CASCADE;


--
-- Name: artifact_workflow_notify_event_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY artifact_workflow_notify
    ADD CONSTRAINT artifact_workflow_notify_event_id_fkey FOREIGN KEY (event_id) REFERENCES artifact_workflow_event(event_id) ON DELETE CASCADE;


--
-- Name: artifact_workflow_roles_event_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY artifact_workflow_roles
    ADD CONSTRAINT artifact_workflow_roles_event_id_fkey FOREIGN KEY (event_id) REFERENCES artifact_workflow_event(event_id) ON DELETE CASCADE;


--
-- Name: artifactfile_artifactid_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY artifact_file
    ADD CONSTRAINT artifactfile_artifactid_fk FOREIGN KEY (artifact_id) REFERENCES artifact(artifact_id) MATCH FULL;


--
-- Name: artifactfile_submittedby_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY artifact_file
    ADD CONSTRAINT artifactfile_submittedby_fk FOREIGN KEY (submitted_by) REFERENCES users(user_id) MATCH FULL;


--
-- Name: artifactgroup_groupid_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY artifact_group_list
    ADD CONSTRAINT artifactgroup_groupid_fk FOREIGN KEY (group_id) REFERENCES groups(group_id) MATCH FULL;


--
-- Name: artifacthistory_artifactid_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY artifact_history
    ADD CONSTRAINT artifacthistory_artifactid_fk FOREIGN KEY (artifact_id) REFERENCES artifact(artifact_id) MATCH FULL;


--
-- Name: artifacthistory_modby_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY artifact_history
    ADD CONSTRAINT artifacthistory_modby_fk FOREIGN KEY (mod_by) REFERENCES users(user_id) MATCH FULL;


--
-- Name: artifactmessage_artifactid_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY artifact_message
    ADD CONSTRAINT artifactmessage_artifactid_fk FOREIGN KEY (artifact_id) REFERENCES artifact(artifact_id) MATCH FULL;


--
-- Name: artifactmessage_submittedby_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY artifact_message
    ADD CONSTRAINT artifactmessage_submittedby_fk FOREIGN KEY (submitted_by) REFERENCES users(user_id) MATCH FULL;


--
-- Name: artifactmonitor_artifactid_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY artifact_monitor
    ADD CONSTRAINT artifactmonitor_artifactid_fk FOREIGN KEY (artifact_id) REFERENCES artifact(artifact_id) MATCH FULL;


--
-- Name: artquery_groupartid_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY artifact_query
    ADD CONSTRAINT artquery_groupartid_fk FOREIGN KEY (group_artifact_id) REFERENCES artifact_group_list(group_artifact_id) ON DELETE CASCADE;


--
-- Name: artqueryelmnt_artqueryid; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY artifact_query_fields
    ADD CONSTRAINT artqueryelmnt_artqueryid FOREIGN KEY (artifact_query_id) REFERENCES artifact_query(artifact_query_id) ON DELETE CASCADE;


--
-- Name: docdata_docgroupid; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY doc_data
    ADD CONSTRAINT docdata_docgroupid FOREIGN KEY (doc_group) REFERENCES doc_groups(doc_group);


--
-- Name: docdata_groupid; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY doc_data
    ADD CONSTRAINT docdata_groupid FOREIGN KEY (group_id) REFERENCES groups(group_id) ON DELETE CASCADE;


--
-- Name: docdata_languageid_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY doc_data
    ADD CONSTRAINT docdata_languageid_fk FOREIGN KEY (language_id) REFERENCES supported_languages(language_id) MATCH FULL;


--
-- Name: docdata_stateid; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY doc_data
    ADD CONSTRAINT docdata_stateid FOREIGN KEY (stateid) REFERENCES doc_states(stateid);


--
-- Name: docgroups_groupid; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY doc_groups
    ADD CONSTRAINT docgroups_groupid FOREIGN KEY (group_id) REFERENCES groups(group_id) ON DELETE CASCADE;


--
-- Name: forum_group_forum_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY forum
    ADD CONSTRAINT forum_group_forum_id_fk FOREIGN KEY (group_forum_id) REFERENCES forum_group_list(group_forum_id) MATCH FULL;


--
-- Name: forum_group_list_group_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY forum_group_list
    ADD CONSTRAINT forum_group_list_group_id_fk FOREIGN KEY (group_id) REFERENCES groups(group_id) MATCH FULL;


--
-- Name: forum_groupforumid; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY forum
    ADD CONSTRAINT forum_groupforumid FOREIGN KEY (group_forum_id) REFERENCES forum_group_list(group_forum_id) MATCH FULL ON DELETE CASCADE;


--
-- Name: forum_pending_attachment_msg_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY forum_pending_attachment
    ADD CONSTRAINT forum_pending_attachment_msg_id_fkey FOREIGN KEY (msg_id) REFERENCES forum_pending_messages(msg_id) ON DELETE CASCADE;


--
-- Name: forum_pending_attachment_userid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY forum_pending_attachment
    ADD CONSTRAINT forum_pending_attachment_userid_fkey FOREIGN KEY (userid) REFERENCES users(user_id) ON DELETE SET DEFAULT;


--
-- Name: forum_pending_messages_group_forum_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY forum_pending_messages
    ADD CONSTRAINT forum_pending_messages_group_forum_id_fkey FOREIGN KEY (group_forum_id) REFERENCES forum_group_list(group_forum_id) ON DELETE CASCADE;


--
-- Name: forum_posted_by_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY forum
    ADD CONSTRAINT forum_posted_by_fk FOREIGN KEY (posted_by) REFERENCES users(user_id) MATCH FULL;


--
-- Name: forum_userid; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY forum
    ADD CONSTRAINT forum_userid FOREIGN KEY (posted_by) REFERENCES users(user_id) MATCH FULL;


--
-- Name: forumgrouplist_groupid; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY forum_group_list
    ADD CONSTRAINT forumgrouplist_groupid FOREIGN KEY (group_id) REFERENCES groups(group_id) MATCH FULL ON DELETE CASCADE;


--
-- Name: frsfile_processorid_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY frs_file
    ADD CONSTRAINT frsfile_processorid_fk FOREIGN KEY (processor_id) REFERENCES frs_processor(processor_id) MATCH FULL;


--
-- Name: frsfile_releaseid_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY frs_file
    ADD CONSTRAINT frsfile_releaseid_fk FOREIGN KEY (release_id) REFERENCES frs_release(release_id) MATCH FULL;


--
-- Name: frsfile_typeid_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY frs_file
    ADD CONSTRAINT frsfile_typeid_fk FOREIGN KEY (type_id) REFERENCES frs_filetype(type_id) MATCH FULL;


--
-- Name: frspackage_groupid_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY frs_package
    ADD CONSTRAINT frspackage_groupid_fk FOREIGN KEY (group_id) REFERENCES groups(group_id) MATCH FULL;


--
-- Name: frspackage_statusid_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY frs_package
    ADD CONSTRAINT frspackage_statusid_fk FOREIGN KEY (status_id) REFERENCES frs_status(status_id) MATCH FULL;


--
-- Name: frsrelease_packageid_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY frs_release
    ADD CONSTRAINT frsrelease_packageid_fk FOREIGN KEY (package_id) REFERENCES frs_package(package_id) MATCH FULL;


--
-- Name: frsrelease_releasedby_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY frs_release
    ADD CONSTRAINT frsrelease_releasedby_fk FOREIGN KEY (released_by) REFERENCES users(user_id) MATCH FULL;


--
-- Name: frsrelease_statusid_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY frs_release
    ADD CONSTRAINT frsrelease_statusid_fk FOREIGN KEY (status_id) REFERENCES frs_status(status_id) MATCH FULL;


--
-- Name: group_id; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY group_activity_monitor
    ADD CONSTRAINT group_id FOREIGN KEY (group_id) REFERENCES groups(group_id) ON DELETE CASCADE;


--
-- Name: group_plugin_group_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY group_plugin
    ADD CONSTRAINT group_plugin_group_id_fk FOREIGN KEY (group_id) REFERENCES groups(group_id) MATCH FULL;


--
-- Name: group_plugin_plugin_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY group_plugin
    ADD CONSTRAINT group_plugin_plugin_id_fk FOREIGN KEY (plugin_id) REFERENCES plugins(plugin_id) MATCH FULL;


--
-- Name: mail_group_list_group_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY mail_group_list
    ADD CONSTRAINT mail_group_list_group_id_fkey FOREIGN KEY (group_id) REFERENCES groups(group_id) ON DELETE CASCADE;


--
-- Name: projcat_projgroupid_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY project_category
    ADD CONSTRAINT projcat_projgroupid_fk FOREIGN KEY (group_project_id) REFERENCES project_group_list(group_project_id) ON DELETE CASCADE;


--
-- Name: project_group_list_group_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY project_group_list
    ADD CONSTRAINT project_group_list_group_id_fk FOREIGN KEY (group_id) REFERENCES groups(group_id) MATCH FULL;


--
-- Name: project_messages_posted_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY project_messages
    ADD CONSTRAINT project_messages_posted_by_fkey FOREIGN KEY (posted_by) REFERENCES users(user_id);


--
-- Name: project_messages_project_task_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY project_messages
    ADD CONSTRAINT project_messages_project_task_id_fkey FOREIGN KEY (project_task_id) REFERENCES project_task(project_task_id) ON DELETE CASCADE;


--
-- Name: project_task_category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY project_task
    ADD CONSTRAINT project_task_category_id_fkey FOREIGN KEY (category_id) REFERENCES project_category(category_id);


--
-- Name: project_task_created_by_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY project_task
    ADD CONSTRAINT project_task_created_by_fk FOREIGN KEY (created_by) REFERENCES users(user_id) MATCH FULL;


--
-- Name: project_task_status_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY project_task
    ADD CONSTRAINT project_task_status_id_fk FOREIGN KEY (status_id) REFERENCES project_status(status_id) MATCH FULL;


--
-- Name: projecttask_groupprojectid_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY project_task
    ADD CONSTRAINT projecttask_groupprojectid_fk FOREIGN KEY (group_project_id) REFERENCES project_group_list(group_project_id) ON DELETE CASCADE;


--
-- Name: projtaskartifact_artifactid_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY project_task_artifact
    ADD CONSTRAINT projtaskartifact_artifactid_fk FOREIGN KEY (artifact_id) REFERENCES artifact(artifact_id) ON DELETE CASCADE;


--
-- Name: projtaskartifact_projtaskid_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY project_task_artifact
    ADD CONSTRAINT projtaskartifact_projtaskid_fk FOREIGN KEY (project_task_id) REFERENCES project_task(project_task_id) ON DELETE CASCADE;


--
-- Name: reptimetrk_timecode; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY rep_time_tracking
    ADD CONSTRAINT reptimetrk_timecode FOREIGN KEY (time_code) REFERENCES rep_time_category(time_code);


--
-- Name: rolesetting_roleroleid; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY role_setting
    ADD CONSTRAINT rolesetting_roleroleid FOREIGN KEY (role_id) REFERENCES role(role_id) ON DELETE CASCADE;


--
-- Name: skills_data_type_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY skills_data
    ADD CONSTRAINT skills_data_type_fkey FOREIGN KEY (type) REFERENCES skills_data_types(type_id);


--
-- Name: skills_data_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY skills_data
    ADD CONSTRAINT skills_data_user_id_fkey FOREIGN KEY (user_id) REFERENCES users(user_id);


--
-- Name: tgl_cat_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY trove_group_link
    ADD CONSTRAINT tgl_cat_id_fk FOREIGN KEY (trove_cat_id) REFERENCES trove_cat(trove_cat_id) MATCH FULL;


--
-- Name: tgl_group_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY trove_group_link
    ADD CONSTRAINT tgl_group_id_fk FOREIGN KEY (group_id) REFERENCES groups(group_id) MATCH FULL;


--
-- Name: trove_agg_cat_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY trove_agg
    ADD CONSTRAINT trove_agg_cat_id_fk FOREIGN KEY (trove_cat_id) REFERENCES trove_cat(trove_cat_id) MATCH FULL;


--
-- Name: trove_agg_group_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY trove_agg
    ADD CONSTRAINT trove_agg_group_id_fk FOREIGN KEY (group_id) REFERENCES groups(group_id) MATCH FULL;


--
-- Name: trove_treesums_cat_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY trove_treesums
    ADD CONSTRAINT trove_treesums_cat_id_fk FOREIGN KEY (trove_cat_id) REFERENCES trove_cat(trove_cat_id) MATCH FULL;


--
-- Name: user_group_group_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY user_group
    ADD CONSTRAINT user_group_group_id_fk FOREIGN KEY (group_id) REFERENCES groups(group_id) MATCH FULL;


--
-- Name: user_group_user_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY user_group
    ADD CONSTRAINT user_group_user_id_fk FOREIGN KEY (user_id) REFERENCES users(user_id) MATCH FULL;


--
-- Name: user_plugin_plugin_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY user_plugin
    ADD CONSTRAINT user_plugin_plugin_id_fk FOREIGN KEY (plugin_id) REFERENCES plugins(plugin_id) MATCH FULL;


--
-- Name: user_plugin_user_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY user_plugin
    ADD CONSTRAINT user_plugin_user_id_fk FOREIGN KEY (user_id) REFERENCES users(user_id) MATCH FULL;


--
-- Name: usergroup_roleid; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY user_group
    ADD CONSTRAINT usergroup_roleid FOREIGN KEY (role_id) REFERENCES role(role_id) MATCH FULL;


--
-- Name: userid_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY group_activity_monitor
    ADD CONSTRAINT userid_fk FOREIGN KEY (user_id) REFERENCES users(user_id);


--
-- Name: users_ccode; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_ccode FOREIGN KEY (ccode) REFERENCES country_code(ccode) MATCH FULL;


--
-- Name: users_languageid_fk; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_languageid_fk FOREIGN KEY (language) REFERENCES supported_languages(language_id) MATCH FULL;


--
-- Name: users_themeid; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_themeid FOREIGN KEY (theme_id) REFERENCES themes(theme_id) MATCH FULL;


--
-- Name: users_typeid; Type: FK CONSTRAINT; Schema: public; Owner: fusionforge
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_typeid FOREIGN KEY (type_id) REFERENCES user_type(type_id) MATCH FULL;


--
-- Name: public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

