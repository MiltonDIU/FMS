export interface Publication {
  id: string | number;
  title: string;
  authors?: string;
  type?: string; // E.g. 'Journal' | 'Conference' | 'Book' | 'Patent' | 'Journal Article'
  venue?: string; // maps to journal_name / venue
  journal_name?: string; // API field
  journal_link?: string; // API field
  publication_year?: string | number; // API field
  year?: number | string; // maps to publication_year / year
  doi?: string;
  abstract?: string | null;
  publisher?: string;
  citations?: number;
  link?: string;
  author_role?: string;
}

export interface JobExperience {
  id: string | number;
  role?: string;
  position?: string; // API field mapping to role
  institution?: string;
  organization?: string; // API field mapping to institution
  duration?: string;
  start_date?: string;
  end_date?: string;
  is_current?: boolean;
  description?: string | null;
}

export interface TrainingExperience {
  id: string | number;
  title: string;
  organization: string;
  year?: number | string | null;
  duration?: string;
  duration_days?: number | null;
  completion_date?: string | null;
  category?: string;
  description?: string | null;
}

export interface Award {
  id: string | number;
  title: string;
  organization?: string;
  awarding_body?: string; // API field mapping to organization
  year?: number | string | null;
  category?: string;
  type?: string; // API field mapping to category
  remarks?: string | null;
}

export interface AcademicDegree {
  id?: string | number;
  degree?: string; // degree_type
  degree_type?: string; // API field
  institution: string;
  year?: number | string | null;
  passing_year?: string | null; // API field mapping to year
  result?: string | null;
  grade?: string | null; // API field mapping to result
  major?: string;
  country?: string | null;
}

export interface ResearchProject {
  id: string | number;
  title: string;
  role: string;
  fundingBody?: string;
  amount?: string;
  status?: string; // 'Ongoing' | 'Completed'
  duration?: string;
}

export interface ThesisSupervision {
  id: string | number;
  title: string;
  studentName: string;
  program?: string; // 'PhD' | 'MSc' | 'BSc'
  year?: number | string;
  status?: string;
}

export interface Consultancy {
  id: string | number;
  organization: string;
  projectTitle: string;
  year?: number | string;
  role?: string;
}

export interface CommunityService {
  id: string | number;
  role: string;
  organization: string;
  duration?: string;
}

export interface Membership {
  id?: string | number;
  organization: string;
  membership_type?: string;
  record_type?: string;
  position?: string;
  scope?: string;
  status?: string;
  is_active?: boolean;
}

export interface SocialLink {
  id: string | number;
  platform: string;
  username?: string;
  url: string;
}

export interface Skill {
  id?: string | number;
  name: string;
  proficiency?: string;
}

export interface TeachingArea {
  id?: string | number;
  area: string;
  description?: string | null;
}

export interface Teacher {
  id: string | number;
  employee_id?: string;
  webpage?: string;
  first_name?: string;
  last_name?: string;
  name: string;
  avatar?: string;
  photo?: string | null; // API field mapping to avatar
  designation: string; // E.g., 'Professor', etc.
  administrativeRole?: string;
  administrative_roles?: any[]; // API field
  departmentId?: string | number;
  department_id?: string | number;
  middle_name?: string | null;
  extension_no?: string | null;
  date_of_birth?: string | null;
  gender?: string | null;
  blood_group?: string | null;
  country?: string | null;
  religion?: string | null;
  present_address?: string | null;
  permanent_address?: string | null;
  joining_date?: string | null;
  work_location?: string | null;
  profile_status?: string | null;
  is_public?: boolean;
  is_active?: boolean;
  login_allowed?: boolean;
  employment_status?: string | null;
  job_type?: string | null;
  is_archived?: boolean;
  sort_order?: number;
  designation_sort_order?: number;
  certifications?: any[];
  skills?: any[];
  department?: {
    id: string | number;
    name: string;
  };
  email: string | null;
  phone: string | null;
  personal_phone?: string | null;
  office?: string;
  office_room?: string | null; // API field mapping to office
  website?: string;
  linkedin?: string;
  googleScholar?: string;
  researchGate?: string;
  facebook?: string;
  github?: string;
  instagram?: string;
  bio: string | null;
  teachingAreas?: (string | TeachingArea)[];
  researchInterests?: string[];
  research_interest?: string | null; // API field
  publications?: Publication[];
  jobExperiences?: JobExperience[];
  trainingExperiences?: TrainingExperience[];
  awards?: Award[];
  memberships?: (string | Membership)[];
  academicBackground?: AcademicDegree[];
  educations?: AcademicDegree[]; // API field mapping to academicBackground
  researchProjects?: ResearchProject[];
  thesisSupervisions?: ThesisSupervision[];
  consultancies?: Consultancy[];
  communityServices?: CommunityService[];
  social_links?: SocialLink[]; // API field
}

export interface Department {
  id: string | number;
  faculty_id?: string | number; // API field
  facultyId?: string | number; // support existing
  name: string;
  short_name?: string;
  code: string;
  is_active?: boolean;
  sort_order?: number;
  description?: string;
}

export interface Faculty {
  id: string | number;
  name: string;
  short_name?: string;
  code: string;
  is_active?: boolean;
  sort_order?: number;
  deanId?: string | number;
  description?: string;
  image?: string;
}

export interface Designation {
  id: number;
  erp_id?: any;
  name: string;
  short_name: string;
  rank: number;
  description: string;
  is_active: boolean;
  sort_order: number;
  teachers_count?: number;
}

export interface AdministrativeRole {
  id: number;
  name: string;
  short_name: string;
  scope: string;
  rank: number;
  description: string;
  is_active: boolean;
  sort_order: number;
}
