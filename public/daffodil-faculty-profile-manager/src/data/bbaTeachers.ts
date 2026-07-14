import { Teacher, Department, Faculty, Designation, AdministrativeRole } from '../types';

export const PROVIDED_ADMINISTRATIVE_ROLES: AdministrativeRole[] = [
  {
    id: 5,
    name: "Dean",
    short_name: "Dean",
    scope: "faculty",
    rank: 5,
    description: "Academic and administrative head of a faculty. Responsible for faculty governance, curriculum development, faculty recruitment, budget management, and maintaining academic standards across all departments in the faculty.",
    is_active: true,
    sort_order: 5
  },
  {
    id: 6,
    name: "Associate Dean",
    short_name: "Assoc. Dean",
    scope: "faculty",
    rank: 6,
    description: "Assists the Dean in faculty administration with specific responsibilities such as academic programs, research, or student affairs. Represents the Dean in meetings and committees when needed.",
    is_active: true,
    sort_order: 6
  },
  {
    id: 7,
    name: "Head of Department",
    short_name: "HoD",
    scope: "department",
    rank: 7,
    description: "Academic and administrative leader of a department. Responsible for department operations, course scheduling, faculty workload, student issues, curriculum updates, and departmental budget. Reports to the Dean.",
    is_active: true,
    sort_order: 7
  }
];

export const PROVIDED_DESIGNATIONS: Designation[] = [
  {
    id: 1,
    name: "Professor",
    short_name: "Prof.",
    rank: 1,
    description: "Highest academic rank requiring Ph.D. with 15+ years of teaching experience. Responsibilities include leading research programs, mentoring junior faculty, curriculum development, and representing the department in academic bodies. Expected to have significant publications and research grants.",
    is_active: true,
    sort_order: 1,
    teachers_count: 37
  },
  {
    id: 2,
    name: "Associate Professor",
    short_name: "Assoc. Prof.",
    rank: 2,
    description: "Senior academic position requiring Ph.D. with 10+ years of experience. Responsibilities include conducting research, supervising graduate students, teaching graduate/undergraduate courses, and contributing to departmental administration. Expected to have regular publications.",
    is_active: true,
    sort_order: 2,
    teachers_count: 11
  },
  {
    id: 3,
    name: "Assistant Professor",
    short_name: "Asst. Prof.",
    rank: 3,
    description: "Mid-level academic position requiring Ph.D. or terminal degree with 5+ years of experience. Responsibilities include teaching undergraduate/graduate courses, conducting research, publishing papers, and participating in departmental activities.",
    is_active: true,
    sort_order: 3,
    teachers_count: 20
  },
  {
    id: 4,
    name: "Senior Lecturer",
    short_name: "Sr. Lect.",
    rank: 4,
    description: "Experienced teaching position requiring Master's degree with 5+ years of teaching experience. Primarily focused on teaching excellence, course development, student mentoring, and may supervise undergraduate projects. Research involvement is encouraged.",
    is_active: true,
    sort_order: 4,
    teachers_count: 2
  },
  {
    id: 5,
    name: "Lecturer",
    short_name: "Lect.",
    rank: 5,
    description: "Entry-level teaching position requiring Master's degree. Responsibilities include teaching undergraduate courses, assisting in laboratory sessions, grading assignments, and contributing to departmental activities. Encouraged to pursue higher degrees.",
    is_active: true,
    sort_order: 5,
    teachers_count: 17
  },
  {
    id: 6,
    name: "Adjunct Faculty",
    short_name: "Adj.",
    rank: 6,
    description: "Part-time or visiting faculty position for industry professionals or academics from other institutions. Responsibilities include teaching specific courses, sharing industry expertise, and providing practical insights to students.",
    is_active: true,
    sort_order: 6,
    teachers_count: 23
  }
];

export const PROVIDED_FACULTIES: Faculty[] = [
  {
    id: 'FBE',
    name: "Faculty of Business & Entrepreneurship",
    short_name: "FBE",
    code: "FBE",
    is_active: true,
    sort_order: 1,
    description: "Developing industry leaders and next-generation innovators with a strong foundation in business administration and strategic entrepreneurship.",
    image: "https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&q=80&w=400"
  },
  {
    id: 'FSIT',
    name: "Faculty of Science & Information Technology",
    short_name: "FSIT",
    code: "FSIT",
    is_active: true,
    sort_order: 1,
    description: "Leading faculty at DIU driving cutting-edge computer science, software engineering, and mathematical computing programs in Bangladesh.",
    image: "https://images.unsplash.com/photo-1517694712202-14dd9538aa97?auto=format&fit=crop&q=80&w=400"
  },
  {
    id: 'FE',
    name: "Faculty of Engineering",
    short_name: "FE",
    code: "FE",
    is_active: true,
    sort_order: 2,
    description: "Nurturing future engineers in telecommunication, textile science, civil, and electrical engineering with robust laboratory-focused education.",
    image: "https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?auto=format&fit=crop&q=80&w=400"
  },
  {
    id: 'FHLS',
    name: "Faculty of Health and Life Sciences",
    short_name: "FHLS",
    code: "FHLS",
    is_active: true,
    sort_order: 3,
    description: "Fostering excellence in health education, pharmaceutical research, public health, and nutrition sciences to meet global healthcare challenges.",
    image: "https://images.unsplash.com/photo-1506126613408-eca07ce68773?auto=format&fit=crop&q=80&w=400"
  },
  {
    id: 'FHSS',
    name: "Faculty of Humanities & Social Sciences",
    short_name: "FHSS",
    code: "FHSS",
    is_active: true,
    sort_order: 4,
    description: "Cultivating critical thinking, professional journalism, and strong English communication skills to prepare graduates for a globalized world.",
    image: "https://images.unsplash.com/photo-1513258496099-48168024aec0?auto=format&fit=crop&q=80&w=400"
  }
];

export const PROVIDED_DEPARTMENTS: Department[] = [
  // FBE
  {
    id: 'dept-ba',
    faculty_id: 1,
    facultyId: 'FBE',
    name: "Department of Business Administration",
    short_name: "BBA",
    code: "BBA",
    is_active: true,
    sort_order: 1,
    description: "Preparing innovative business professionals with expertise in marketing, finance, and human resource management."
  },
  {
    id: 'dept-mgt',
    faculty_id: 1,
    facultyId: 'FBE',
    name: "Department of Management",
    short_name: "MGT",
    code: "MGT",
    is_active: true,
    sort_order: 2,
    description: "Managing corporate architectures, strategic development, and operational efficiencies."
  },
  {
    id: 'dept-re',
    faculty_id: 1,
    facultyId: 'FBE',
    name: "Department of Real Estate",
    short_name: "RE",
    code: "RE",
    is_active: true,
    sort_order: 3,
    description: "Urban development planning, valuation modeling, and real estate investment portfolio strategies."
  },
  {
    id: 'dept-thm',
    faculty_id: 1,
    facultyId: 'FBE',
    name: "Department of Tourism & Hospitality Management",
    short_name: "THM",
    code: "THM",
    is_active: true,
    sort_order: 4,
    description: "Preparing leaders for the global hospitality and international tourism networks."
  },
  {
    id: 'dept-ie',
    faculty_id: 1,
    facultyId: 'FBE',
    name: "Department of Innovation & Entrepreneurship",
    short_name: "IE",
    code: "IE",
    is_active: true,
    sort_order: 5,
    description: "Accelerating venture ideas, incubation strategies, and social business models."
  },
  {
    id: 'dept-acc',
    faculty_id: 1,
    facultyId: 'FBE',
    name: "Department of Accounting",
    short_name: "ACC",
    code: "ACC",
    is_active: true,
    sort_order: 6,
    description: "Strategic financial audits, accounting information systems, and corporate compliance."
  },
  {
    id: 'dept-fnb',
    faculty_id: 1,
    facultyId: 'FBE',
    name: "Department of Finance & Banking",
    short_name: "FNB",
    code: "FNB",
    is_active: true,
    sort_order: 7,
    description: "Asset valuation, financial institutions modeling, risk management, and capital audits."
  },
  {
    id: 'dept-mkt',
    faculty_id: 1,
    facultyId: 'FBE',
    name: "Department of Marketing",
    short_name: "MKT",
    code: "MKT",
    is_active: true,
    sort_order: 8,
    description: "Consumer behaviors, digital brand management, and multi-channel marketing campaigns."
  },

  // FSIT
  {
    id: 'dept-cse',
    faculty_id: 5,
    facultyId: 'FSIT',
    name: "Computer Science & Engineering",
    short_name: "CSE",
    code: "CSE",
    is_active: true,
    sort_order: 1,
    description: "The Department of Computer Science and Engineering (CSE) is the largest and most prestigious department of DIU, producing top-tier tech professionals."
  },
  {
    id: 'dept-swe',
    faculty_id: 5,
    facultyId: 'FSIT',
    name: "Department of Software Engineering",
    short_name: "SWE",
    code: "SWE",
    is_active: true,
    sort_order: 27,
    description: "Pioneering specialized software development, agile methodologies, and industry-oriented system engineering programs."
  },
  {
    id: 'dept-mct',
    faculty_id: 5,
    facultyId: 'FSIT',
    name: "Department of Multimedia & Creative Technology",
    short_name: "MCT",
    code: "MCT",
    is_active: true,
    sort_order: 28,
    description: "Preparing creative engineers for game design, 3D animation, VFX, and digital media production."
  },
  {
    id: 'dept-cis',
    faculty_id: 5,
    facultyId: 'FSIT',
    name: "Department of Computing and Information System",
    short_name: "CIS",
    code: "CIS",
    is_active: true,
    sort_order: 29,
    description: "Focusing on strategic implementation of information technology systems, cloud operations, and security protocols."
  },
  {
    id: 'dept-itm',
    faculty_id: 5,
    facultyId: 'FSIT',
    name: "Department of Information Technology & Management",
    short_name: "ITM",
    code: "ITM",
    is_active: true,
    sort_order: 30,
    description: "Bridging technical data systems and modern corporate information flow strategies."
  }
];
