import { Faculty, Department, Teacher } from '../types';

export const INITIAL_FACULTIES: Faculty[] = [
  {
    id: 'fac-1',
    name: 'Faculty of Science & Information Technology',
    code: 'FSIT',
    deanId: 't-1',
    description: 'Leading faculty at DIU driving cutting-edge computer science, software engineering, and mathematical computing programs in Bangladesh.',
    image: 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?auto=format&fit=crop&q=80&w=400'
  },
  {
    id: 'fac-2',
    name: 'Faculty of Business & Entrepreneurship',
    code: 'FBE',
    deanId: 't-4',
    description: 'Developing industry leaders and next-generation innovators with a strong foundation in business administration and strategic entrepreneurship.',
    image: 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&q=80&w=400'
  },
  {
    id: 'fac-3',
    name: 'Faculty of Engineering',
    code: 'FE',
    deanId: 't-6',
    description: 'Nurturing future engineers in telecommunication, textile science, civil, and electrical engineering with robust laboratory-focused education.',
    image: 'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?auto=format&fit=crop&q=80&w=400'
  },
  {
    id: 'fac-4',
    name: 'Faculty of Humanities & Social Sciences',
    code: 'FHSS',
    deanId: 't-8',
    description: 'Cultivating critical thinking, professional journalism, and strong English communication skills to prepare graduates for a globalized world.',
    image: 'https://images.unsplash.com/photo-1513258496099-48168024aec0?auto=format&fit=crop&q=80&w=400'
  },
  {
    id: 'fac-5',
    name: 'Faculty of Health and Life Sciences',
    code: 'FHLS',
    deanId: 't-14',
    description: 'Fostering excellence in health education, pharmaceutical research, public health, and nutrition sciences to meet global healthcare challenges.',
    image: 'https://images.unsplash.com/photo-1506126613408-eca07ce68773?auto=format&fit=crop&q=80&w=400'
  }
];

export const INITIAL_DEPARTMENTS: Department[] = [
  // FSIT
  {
    id: 'dept-cse',
    name: 'Computer Science and Engineering',
    code: 'CSE',
    facultyId: 'fac-1',
    description: 'The Department of Computer Science and Engineering (CSE) is the largest and most prestigious department of DIU, producing top-tier tech professionals.'
  },
  {
    id: 'dept-swe',
    name: 'Software Engineering',
    code: 'SWE',
    facultyId: 'fac-1',
    description: 'Pioneering specialized software development, agile methodologies, and industry-oriented system engineering programs.'
  },
  {
    id: 'dept-mct',
    name: 'Multimedia & Creative Technology',
    code: 'MCT',
    facultyId: 'fac-1',
    description: 'Preparing creative engineers for game design, 3D animation, VFX, and digital media production.'
  },
  {
    id: 'dept-cis',
    name: 'Computing & Information System',
    code: 'CIS',
    facultyId: 'fac-1',
    description: 'Focusing on strategic implementation of information technology systems, cloud operations, and security protocols.'
  },
  {
    id: 'dept-itm',
    name: 'Information Technology and Management',
    code: 'ITM',
    facultyId: 'fac-1',
    description: 'Bridging technical data systems and modern corporate information flow strategies.'
  },
  {
    id: 'dept-esdm',
    name: 'Environmental Science and Disaster Management',
    code: 'ESDM',
    facultyId: 'fac-1',
    description: 'Addressing sustainability, climate change resilience, ecological engineering, and safety protocols.'
  },
  {
    id: 'dept-pess',
    name: 'Physical Education and Sports Science',
    code: 'PESS',
    facultyId: 'fac-1',
    description: 'Developing leadership in physical fitness management, scientific athletic coaching, and kinesiology.'
  },
  // FBE
  {
    id: 'dept-ba',
    name: 'Business Administration',
    code: 'BA',
    facultyId: 'fac-2',
    description: 'Preparing innovative business professionals with expertise in marketing, finance, and human resource management.'
  },
  {
    id: 'dept-thm',
    name: 'Tourism & Hospitality Management',
    code: 'THM',
    facultyId: 'fac-2',
    description: 'Shaping specialists for the international hospitality and global tourism industry.'
  },
  {
    id: 'dept-re',
    name: 'Real Estate',
    code: 'RE',
    facultyId: 'fac-2',
    description: 'Pioneering professional degrees in urban property valuation, real estate finance, and housing developments.'
  },
  {
    id: 'dept-ie',
    name: 'Innovation & Entrepreneurship',
    code: 'I&E',
    facultyId: 'fac-2',
    description: 'Fostering venture builders, incubator management, and dynamic startup architectures for Bangladesh.'
  },
  // FE
  {
    id: 'dept-eee',
    name: 'Electrical and Electronic Engineering',
    code: 'EEE',
    facultyId: 'fac-3',
    description: 'Conducting high-impact research in renewable energy, power grids, and smart electronics.'
  },
  {
    id: 'dept-te',
    name: 'Textile Engineering',
    code: 'TE',
    facultyId: 'fac-3',
    description: 'Advancing sustainable fabrics, fashion merchandising, and industrial production workflows.'
  },
  {
    id: 'dept-ce',
    name: 'Civil Engineering',
    code: 'CE',
    facultyId: 'fac-3',
    description: 'Providing comprehensive education in smart highway design, structural resilience, and water management systems.'
  },
  {
    id: 'dept-arch',
    name: 'Architecture',
    code: 'Arch',
    facultyId: 'fac-3',
    description: 'Nurturing aesthetic design, green architectural planning, historic conservation, and spatial dynamics.'
  },
  // FHSS
  {
    id: 'dept-eng',
    name: 'English',
    code: 'ENG',
    facultyId: 'fac-4',
    description: 'Enhancing linguistic capabilities, literature appreciation, and professional writing skills.'
  },
  {
    id: 'dept-jmc',
    name: 'Journalism, Media and Communication',
    code: 'JMC',
    facultyId: 'fac-4',
    description: 'Fostering digital media competencies, public relations, and ethical journalism in Bangladesh.'
  },
  {
    id: 'dept-law',
    name: 'Law',
    code: 'LAW',
    facultyId: 'fac-4',
    description: 'Preparing legal experts, human rights advocates, and corporate legal counsels with robust simulated moot court practices.'
  },
  {
    id: 'dept-ds',
    name: 'Development Studies',
    code: 'DS',
    facultyId: 'fac-4',
    description: 'Analyzing global poverty policies, NGO administration, environmental economics, and social reform paradigms.'
  },
  // FHLS
  {
    id: 'dept-pharm',
    name: 'Pharmacy',
    code: 'PHR',
    facultyId: 'fac-5',
    description: 'An elite pharmaceutical science division with cutting-edge laboratories specializing in drug synthesis, clinical analysis, and biopharmaceutics.'
  },
  {
    id: 'dept-ph',
    name: 'Public Health',
    code: 'PH',
    facultyId: 'fac-5',
    description: 'Addressing community epidemiology, healthcare policy formulations, sanitation programs, and global disease management.'
  },
  {
    id: 'dept-nfe',
    name: 'Nutrition and Food Engineering',
    code: 'NFE',
    facultyId: 'fac-5',
    description: 'Integrating food biotechnology, product development, dietary therapy, and safety testing systems.'
  }
];

const BASE_TEACHERS: Teacher[] = [
  {
    id: 'mahbub',
    employee_id: '710001999',
    webpage: 'mahbub',
    first_name: 'Mahbub',
    last_name: 'Parvez',
    name: 'Prof. Dr. Mahbub Parvez',
    avatar: 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&q=80&w=256',
    photo: 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&q=80&w=256',
    designation: 'Professor',
    administrativeRole: 'Head of Department',
    departmentId: 'dept-ba',
    email: 'mahbub.ba@daffodilvarsity.edu.bd',
    phone: '+8801711223344',
    office: 'Level 4, Business Block, DIU Smart City',
    bio: 'Prof. Dr. Mahbub Parvez is a distinguished academician and Professor of Business Administration at Daffodil International University. He has over 20 years of experience in business administration, strategic management, entrepreneurship, and organizational leadership.',
    teachingAreas: ['Strategic Management', 'Entrepreneurship', 'Organizational Behavior', 'Human Resource Management'],
    researchInterests: ['SME Development', 'Social Business', 'Sustainable Entrepreneurship', 'Corporate Governance'],
    publications: [
      {
        id: 'pub-mp-1',
        title: 'Strategic Adaptability of Small and Medium Enterprises (SMEs) in Emerging Economies',
        authors: 'Mahbub Parvez, Imran Mahmud',
        type: 'Journal',
        venue: 'Journal of Business and Entrepreneurship',
        year: 2024,
        doi: '10.5555/jbe.2024.101',
        abstract: 'This paper examines how strategic flexibility and resource configuration impact SME performance during market shifts. It offers a framework for resilient operations in South Asian countries.',
        publisher: 'DIU Press',
        citations: 15,
        link: '#'
      },
      {
        id: 'pub-mp-2',
        title: 'Fostering Entrepreneurial Intentions Among University Graduates: The Role of Incubation Ecosystems',
        authors: 'Mahbub Parvez, Syed Akhter Hossain',
        type: 'Conference',
        venue: 'International Conference on Innovation & Entrepreneurship',
        year: 2023,
        doi: '10.5555/conf.2023.202',
        abstract: 'Analyzing the longitudinal impact of structured university incubators on startup birthrates among graduating cohorts in Bangladesh.',
        publisher: 'IEEE Computer Society',
        citations: 10,
        link: '#'
      }
    ],
    jobExperiences: [
      {
        id: 'exp-mp-1',
        role: 'Professor & Head, Department of Business Administration',
        institution: 'Daffodil International University',
        duration: '2019 - Present',
        description: 'Supervising academic programs, leading board curriculum reviews, and fostering strategic corporate partnerships.'
      },
      {
        id: 'exp-mp-2',
        role: 'Associate Professor',
        institution: 'Daffodil International University',
        duration: '2014 - 2019',
        description: 'Delivered advanced lectures and spearheaded the student business development cell.'
      }
    ],
    trainingExperiences: [
      {
        id: 'trn-mp-1',
        title: 'Outcome-Based Education (OBE) in Higher Business Education',
        organization: 'Institutional Quality Assurance Cell (IQAC)',
        year: 2022,
        duration: '3 Days'
      }
    ],
    awards: [
      {
        id: 'aw-mp-1',
        title: 'Best Academician of the Year',
        organization: 'Daffodil International University',
        year: 2023,
        category: 'Teaching'
      }
    ],
    memberships: [
      'Member, Association of Management Development Institutions in South Asia (AMDISA)',
      'Fellow, Bangladesh Economic Association'
    ],
    academicBackground: [
      {
        degree: 'Ph.D. in Business Administration',
        institution: 'Dhaka University',
        year: 2012,
        result: 'First Class'
      },
      {
        degree: 'MBA in Strategic Management',
        institution: 'Dhaka University',
        year: 2004,
        result: 'First Class'
      }
    ]
  },
  {
    id: 't-1',
    name: 'Prof. Dr. Syed Akhter Hossain',
    avatar: 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&q=80&w=256',
    designation: 'Professor',
    administrativeRole: 'Dean',
    departmentId: 'dept-cse',
    email: 'dean.fsit@daffodilvarsity.edu.bd',
    phone: '+8801712345678',
    office: 'Level 4, Academic Building 1, DIU Smart City',
    website: 'https://akhterhossain.com',
    linkedin: 'https://linkedin.com/in/syed-akhter-hossain',
    googleScholar: 'https://scholar.google.com/citations?user=akhter',
    bio: 'Prof. Dr. Syed Akhter Hossain has over 25 years of teaching, research, and administrative experience in ICT. He has been actively involved in natural language processing (NLP), machine learning, and digital university transformations in Bangladesh.',
    teachingAreas: ['Natural Language Processing', 'Advanced Software Engineering', 'Machine Learning', 'Compiler Design'],
    researchInterests: ['Bengali Language Computing', 'Artificial Intelligence', 'Knowledge Graphs', 'E-Governance'],
    publications: [
      {
        id: 'pub-101',
        title: 'An Optimized Deep Learning Framework for Bengali Text Classification and Sentiment Analysis',
        authors: 'S. A. Hossain, M. A. Rahman, T. Ahmed',
        type: 'Journal',
        venue: 'IEEE Access',
        year: 2024,
        doi: '10.1109/ACCESS.2024.112233',
        abstract: 'This paper presents a novel model incorporating customized LSTM and Transformer networks to classify sentiments in social media posts written in Bengali. The system achieves a state-of-the-art accuracy of 93.4% on large benchmarks.',
        publisher: 'IEEE',
        citations: 45,
        link: '#'
      },
      {
        id: 'pub-102',
        title: 'Machine Translation Framework from English to Bengali using Neural Attention Networks',
        authors: 'S. A. Hossain, R. Islam',
        type: 'Conference',
        venue: 'International Conference on Computer and Information Technology (ICCIT)',
        year: 2023,
        doi: '10.1109/ICCIT.2023.9988',
        abstract: 'An attention-based sequence-to-sequence model designed to handle complex morpho-syntactic constraints in translations from English to Bengali. We outline significant improvements in BLEU scores.',
        publisher: 'IEEE Computer Society',
        citations: 28,
        link: '#'
      }
    ],
    jobExperiences: [
      {
        id: 'exp-101',
        role: 'Dean of Faculty (FSIT)',
        institution: 'Daffodil International University',
        duration: '2020 - Present',
        description: 'Providing visionary academic leadership, initiating curriculum modernization, and establishing industry-university research partnerships.'
      },
      {
        id: 'exp-102',
        role: 'Professor and Head, Department of CSE',
        institution: 'Daffodil International University',
        duration: '2015 - 2020',
        description: 'Supervised 50+ faculty members and established the DIU NLP Research Lab.'
      }
    ],
    trainingExperiences: [
      {
        id: 'trn-101',
        title: 'Outcome-Based Education (OBE) Implementation in Computer Science',
        organization: 'Board of Accreditation for Engineering and Technical Education (BAETE)',
        year: 2022,
        duration: '3 Days'
      },
      {
        id: 'trn-102',
        title: 'Artificial Intelligence and Advanced NLP Architectures',
        organization: 'IIT Kharagpur, India',
        year: 2019,
        duration: '2 Weeks'
      }
    ],
    awards: [
      {
        id: 'aw-101',
        title: 'DIU Best Researcher of the Year',
        organization: 'Daffodil International University',
        year: 2024,
        category: 'Research'
      },
      {
        id: 'aw-102',
        title: 'National ICT Excellence Award',
        organization: 'Ministry of ICT, Bangladesh',
        year: 2021,
        category: 'National'
      }
    ],
    memberships: [
      'Senior Member, IEEE',
      'Fellow, Bangladesh Computer Society (BCS)',
      'Member, Association for Computational Linguistics (ACL)'
    ]
  },
  {
    id: 't-2',
    name: 'Dr. Sheak Rashed Haider Noori',
    avatar: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&q=80&w=256',
    designation: 'Associate Professor',
    administrativeRole: 'Head of Department',
    departmentId: 'dept-cse',
    email: 'headcse@daffodilvarsity.edu.bd',
    phone: '+8801722334455',
    office: 'Level 3, Academic Building 1, DIU Smart City',
    website: 'https://rashednoori.info',
    linkedin: 'https://linkedin.com/in/rashed-noori',
    googleScholar: 'https://scholar.google.com/citations?user=noori',
    bio: 'Dr. Sheak Rashed Haider Noori is a seasoned educator focusing on database management systems, big data analytics, and cloud platforms. He coordinates global research collaborations for the CSE Department.',
    teachingAreas: ['Database Management Systems', 'Big Data Analytics', 'Cloud Computing', 'Data Warehousing'],
    researchInterests: ['Distributed Databases', 'IoT Cloud Frameworks', 'Predictive Analytics in Agriculture'],
    publications: [
      {
        id: 'pub-201',
        title: 'An Adaptive Cloud Ingestion Model for High-Velocity Sensor Streams in Smart Agriculture',
        authors: 'S. R. H. Noori, F. Khan, A. S. M. Farhan',
        type: 'Journal',
        venue: 'Journal of Cloud Computing',
        year: 2023,
        doi: '10.1186/s13677-023-0199-2',
        abstract: 'This research highlights efficient ingestion techniques to minimize latency and server overhead when gathering real-time sensory data from remote fields across rural Bangladesh.',
        publisher: 'Springer',
        citations: 19,
        link: '#'
      }
    ],
    jobExperiences: [
      {
        id: 'exp-201',
        role: 'Associate Professor & Head of CSE',
        institution: 'Daffodil International University',
        duration: '2021 - Present',
        description: 'Managing day-to-day academic schedules, accreditation processes, and student counseling.'
      }
    ],
    trainingExperiences: [
      {
        id: 'trn-201',
        title: 'Active Learning & Pedagogical Innovations',
        organization: 'DIU HRDI',
        year: 2023,
        duration: '5 Days'
      }
    ],
    awards: [
      {
        id: 'aw-201',
        title: 'Outstanding Academic Leader Award',
        organization: 'Daffodil International University',
        year: 2023,
        category: 'Teaching'
      }
    ],
    memberships: [
      'Member, IEEE',
      'Member, ACM',
      'Life Member, Bangladesh Computer Society (BCS)'
    ]
  },
  {
    id: 't-3',
    name: 'Prof. Dr. Imran Mahmud',
    avatar: 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&q=80&w=256',
    designation: 'Professor',
    administrativeRole: 'Head of Department',
    departmentId: 'dept-swe',
    email: 'headswe@daffodilvarsity.edu.bd',
    phone: '+8801733445566',
    office: 'Level 2, Academic Building 2, DIU Smart City',
    website: 'https://imranmahmud.net',
    linkedin: 'https://linkedin.com/in/imranmahmud',
    googleScholar: 'https://scholar.google.com/citations?user=imran',
    bio: 'Prof. Dr. Imran Mahmud specializes in software engineering principles, project management, and information systems. He has published extensively on tech adoption frameworks in developing economies.',
    teachingAreas: ['Software Project Management', 'Enterprise Software Architectures', 'Research Methodology'],
    researchInterests: ['User Acceptance Testing', 'Technology Acceptance Models (TAM)', 'Agile Development Practices'],
    publications: [
      {
        id: 'pub-301',
        title: 'Understanding the Drivers of Mobile Banking Adoption in Bangladesh: A Structural Equation Modeling Approach',
        authors: 'I. Mahmud, K. S. Ahmed, N. Chowdhury',
        type: 'Journal',
        venue: 'International Journal of Information Management',
        year: 2022,
        doi: '10.1016/j.ijinfomgt.2022.10214',
        abstract: 'An empirical research paper exploring how trust, subjective norms, and perceived security impact mobile financial system adoption among rural banking demographics.',
        publisher: 'Elsevier',
        citations: 112,
        link: '#'
      }
    ],
    jobExperiences: [
      {
        id: 'exp-301',
        role: 'Professor & Head, Department of SWE',
        institution: 'Daffodil International University',
        duration: '2019 - Present'
      }
    ],
    trainingExperiences: [
      {
        id: 'trn-301',
        title: 'Structural Equation Modeling using SmartPLS & AMOS',
        organization: 'University of Malaya, Malaysia',
        year: 2018,
        duration: '1 Week'
      }
    ],
    awards: [
      {
        id: 'aw-301',
        title: 'Best Research Paper Award',
        organization: 'APMR Conference',
        year: 2021,
        category: 'Research'
      }
    ],
    memberships: [
      'Association for Information Systems (AIS)',
      'IEEE Computer Society'
    ]
  },
  {
    id: 't-4',
    name: 'Prof. Dr. Md. Masum Iqbal',
    avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=256',
    designation: 'Professor',
    administrativeRole: 'Dean',
    departmentId: 'dept-ba',
    email: 'dean.fbe@daffodilvarsity.edu.bd',
    phone: '+8801744556677',
    office: 'Level 5, Main Admin Tower, DIU Smart City',
    website: 'https://masumiqbal.info',
    linkedin: 'https://linkedin.com/in/md-masum-iqbal',
    googleScholar: 'https://scholar.google.com/citations?user=masumiqbal',
    bio: 'Prof. Dr. Md. Masum Iqbal leads the Faculty of Business & Entrepreneurship. His primary areas of interest include social business, SME development, and fintech models.',
    teachingAreas: ['Strategic Management', 'Entrepreneurship Development', 'International Business'],
    researchInterests: ['Social Business Models', 'SME Financial Hardiness', 'Green Banking Policies'],
    publications: [
      {
        id: 'pub-401',
        title: 'Empowering Women Entrepreneurs through Micro-credits: Evidence from Northern Bangladesh',
        authors: 'M. M. Iqbal, T. J. Parveen',
        type: 'Journal',
        venue: 'Journal of Social Business',
        year: 2023,
        doi: '10.5555/jsb.2023.111',
        abstract: 'A deep-dive investigation into the socioeconomic shift of women leading rural microenterprises, highlighting sustainable credit disbursement programs.',
        publisher: 'Daffodil Press',
        citations: 34,
        link: '#'
      }
    ],
    jobExperiences: [
      {
        id: 'exp-401',
        role: 'Dean of Faculty of Business & Entrepreneurship',
        institution: 'Daffodil International University',
        duration: '2018 - Present'
      }
    ],
    trainingExperiences: [
      {
        id: 'trn-401',
        title: 'Social Business Masterclass',
        organization: 'Yunus Centre, Dhaka',
        year: 2017,
        duration: '3 Days'
      }
    ],
    awards: [
      {
        id: 'aw-401',
        title: 'Social Entrepreneurship Patron Award',
        organization: 'Yunus Foundation',
        year: 2022,
        category: 'National'
      }
    ],
    memberships: [
      'Bangladesh Economic Association',
      'Royal Institute of Management, UK'
    ]
  },
  {
    id: 't-5',
    name: 'Ms. Tanzina Hossain',
    avatar: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=256',
    designation: 'Assistant Professor',
    administrativeRole: 'Coordinator',
    departmentId: 'dept-cse',
    email: 'tanzina@daffodilvarsity.edu.bd',
    phone: '+8801755667788',
    office: 'Level 3, Academic Building 1, DIU Smart City',
    website: '',
    linkedin: 'https://linkedin.com/in/tanzina-hossain',
    googleScholar: '',
    bio: 'Ms. Tanzina Hossain has been a core member of the CSE department since 2018. She coordinates the evening batch programs and student advisory cells.',
    teachingAreas: ['Data Structures and Algorithms', 'Object Oriented Programming', 'Software Requirement Engineering'],
    researchInterests: ['Educational Technology (EdTech)', 'Human-Computer Interaction', 'Predictive Learning Models'],
    publications: [
      {
        id: 'pub-501',
        title: 'Gamification Elements in Local Online LMS Platforms: A Study on Student Engagement at Bangladeshi Universities',
        authors: 'T. Hossain, M. Mahmud',
        type: 'Conference',
        venue: 'International Conference on Advance Learning Technologies (ICALT)',
        year: 2024,
        doi: '10.1109/ICALT.2024.444',
        abstract: 'An examination of how gamified quizzes and leadership badges increase the retention of CSE students using the DIU BLC (SmartEdu) system.',
        publisher: 'IEEE',
        citations: 8,
        link: '#'
      }
    ],
    jobExperiences: [
      {
        id: 'exp-501',
        role: 'Assistant Professor & Evening Coordinator',
        institution: 'Daffodil International University',
        duration: '2022 - Present'
      },
      {
        id: 'exp-502',
        role: 'Senior Lecturer',
        institution: 'Daffodil International University',
        duration: '2019 - 2022'
      }
    ],
    trainingExperiences: [
      {
        id: 'trn-501',
        title: 'Pedagogy & Classroom Engagement in Digital Era',
        organization: 'British Council BD',
        year: 2021,
        duration: '4 Days'
      }
    ],
    awards: [
      {
        id: 'aw-501',
        title: 'DIU Best Advisor Award',
        organization: 'Daffodil International University',
        year: 2023,
        category: 'Teaching'
      }
    ],
    memberships: [
      'Bangladesh Computer Society (BCS)'
    ]
  },
  {
    id: 't-6',
    name: 'Prof. Dr. Md. Fayzur Rahman',
    avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&q=80&w=256',
    designation: 'Professor',
    administrativeRole: 'Dean',
    departmentId: 'dept-eee',
    email: 'dean.fe@daffodilvarsity.edu.bd',
    phone: '+8801766778899',
    office: 'Level 1, Engineering Complex, DIU Smart City',
    website: '',
    linkedin: 'https://linkedin.com/in/fayzur-rahman-eee',
    googleScholar: 'https://scholar.google.com/citations?user=fayzur',
    bio: 'Prof. Dr. Md. Fayzur Rahman has 30+ years of scholarly and industrial exposure in Electrical Systems, Grid stability, and High voltage electronics. He represents DIU at national power energy forums.',
    teachingAreas: ['Electrical Circuits', 'Renewable Energy Systems', 'Power Grid Analysis'],
    researchInterests: ['Solar PV Harvesting', 'Microgrids', 'Power Electronics Control'],
    publications: [
      {
        id: 'pub-601',
        title: 'Optimized MPPT Controllers for Double-Sided Solar Tracking Systems in Tropical Monsoon Regions',
        authors: 'M. F. Rahman, S. K. Das, R. Jahir',
        type: 'Journal',
        venue: 'IEEE Transactions on Sustainable Energy',
        year: 2023,
        doi: '10.1109/TSTE.2023.7777',
        abstract: 'Presents dynamic algorithms that adjust parameters during erratic rainfall patterns, enhancing power harvesting efficiency by up to 14.8%.',
        publisher: 'IEEE',
        citations: 58,
        link: '#'
      }
    ],
    jobExperiences: [
      {
        id: 'exp-601',
        role: 'Dean of Faculty of Engineering',
        institution: 'Daffodil International University',
        duration: '2019 - Present'
      },
      {
        id: 'exp-602',
        role: 'Professor',
        institution: 'BUET',
        duration: '1998 - 2018'
      }
    ],
    trainingExperiences: [
      {
        id: 'trn-601',
        title: 'Smart Power System Automation Masterclass',
        organization: 'Siemens Training Centre, Germany',
        year: 2015,
        duration: '3 Weeks'
      }
    ],
    awards: [
      {
        id: 'aw-601',
        title: 'Life Achievement in Engineering Education',
        organization: 'Institute of Engineers Bangladesh (IEB)',
        year: 2024,
        category: 'National'
      }
    ],
    memberships: [
      'Fellow, Institute of Engineers Bangladesh (IEB)',
      'Senior Member, IEEE Power & Energy Society'
    ]
  },
  {
    id: 't-7',
    name: 'Mr. Rafiqul Islam',
    avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&q=80&w=256',
    designation: 'Senior Lecturer',
    administrativeRole: 'Advisor',
    departmentId: 'dept-cse',
    email: 'rafiqul.cse@daffodilvarsity.edu.bd',
    phone: '+8801777889900',
    office: 'Level 3, Academic Building 1, DIU Smart City',
    website: '',
    linkedin: '',
    googleScholar: '',
    bio: 'Mr. Rafiqul Islam is a dedicated educator teaching core programming paradigms. He handles ACM ICPC team coaching at DIU and conducts programming bootcamps.',
    teachingAreas: ['Discrete Mathematics', 'Advanced Algorithms', 'Competitive Programming'],
    researchInterests: ['Algorithm Optimization', 'Machine Learning on Graph Data'],
    publications: [
      {
        id: 'pub-701',
        title: 'A Faster Shortest Path Approximation over Dynamically Weighted Transit Networks',
        authors: 'R. Islam, A. S. Sadik',
        type: 'Conference',
        venue: 'International Conference on Informatics, Electronics & Vision (ICIEV)',
        year: 2022,
        doi: '10.1109/ICIEV.2022.312',
        abstract: 'An algorithmic design reducing computational complexity for pathfinding on dynamically shifting traffic routing layers.',
        publisher: 'IEEE',
        citations: 12,
        link: '#'
      }
    ],
    jobExperiences: [
      {
        id: 'exp-701',
        role: 'Senior Lecturer & ICPC Coach',
        institution: 'Daffodil International University',
        duration: '2021 - Present'
      },
      {
        id: 'exp-702',
        role: 'Lecturer',
        institution: 'Daffodil International University',
        duration: '2018 - 2021'
      }
    ],
    trainingExperiences: [
      {
        id: 'trn-701',
        title: 'Coaching Competitive Programmers',
        organization: 'ACM Dhaka Regional Committee',
        year: 2020,
        duration: '3 Days'
      }
    ],
    awards: [
      {
        id: 'aw-701',
        title: 'Best Mentor of ACM ICPC Regionals',
        organization: 'ACM-ICPC BD Chapter',
        year: 2023,
        category: 'Teaching'
      }
    ],
    memberships: [
      'Bangladesh Computer Society (BCS)'
    ]
  },
  {
    id: 't-8',
    name: 'Prof. Dr. Liza Sharmin',
    avatar: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&q=80&w=256',
    designation: 'Professor',
    administrativeRole: 'Dean',
    departmentId: 'dept-eng',
    email: 'dean.fhss@daffodilvarsity.edu.bd',
    phone: '+8801788990011',
    office: 'Level 4, Humanities Block, DIU Smart City',
    website: '',
    linkedin: 'https://linkedin.com/in/liza-sharmin',
    googleScholar: '',
    bio: 'Prof. Dr. Liza Sharmin guides the FHSS. Her focus is Applied Linguistics, English Language Teaching (ELT) and interactive communication strategies for professional development.',
    teachingAreas: ['Applied Linguistics', 'English Language Teaching (ELT)', 'Business Communication'],
    researchInterests: ['Linguistic Shifts in Bengali Youth', 'Online Pedagogy for Language Acquisition'],
    publications: [
      {
        id: 'pub-801',
        title: 'Challenges and Strategies in Virtual English Language Classrooms during Post-pandemic Recovery',
        authors: 'L. Sharmin, N. Fatema',
        type: 'Journal',
        venue: 'Bangladesh Journal of English Studies',
        year: 2023,
        doi: '10.5555/bjes.2023.23',
        abstract: 'An intensive research tracing how hybrid learning environments affect speech and listening proficiencies among local undergrads.',
        publisher: 'DIU Press',
        citations: 21,
        link: '#'
      }
    ],
    jobExperiences: [
      {
        id: 'exp-801',
        role: 'Dean of FHSS',
        institution: 'Daffodil International University',
        duration: '2021 - Present'
      },
      {
        id: 'exp-802',
        role: 'Professor & Head of English Department',
        institution: 'Daffodil International University',
        duration: '2016 - 2021'
      }
    ],
    trainingExperiences: [
      {
        id: 'trn-801',
        title: 'Advanced TESOL Curriculum Design',
        organization: 'British Council, Malaysia',
        year: 2018,
        duration: '2 Weeks'
      }
    ],
    awards: [
      {
        id: 'aw-801',
        title: 'Distinguished Educator Award',
        organization: 'Education Board Bangladesh',
        year: 2022,
        category: 'National'
      }
    ],
    memberships: [
      'President, BELTA (Bangladesh English Language Teachers Association)',
      'Member, International Association of Teachers of English as a Foreign Language (IATEFL)'
    ]
  },
  {
    id: 't-9',
    name: 'Mr. Tanvir Rahman',
    avatar: 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&q=80&w=256',
    designation: 'Lecturer',
    administrativeRole: 'None',
    departmentId: 'dept-cse',
    email: 'tanvir.cse@daffodilvarsity.edu.bd',
    phone: '+8801799001122',
    office: 'Level 3, Academic Building 1, DIU Smart City',
    website: '',
    linkedin: '',
    googleScholar: '',
    bio: 'Mr. Tanvir Rahman joined DIU CSE in 2023. He is a passionate young researcher specializing in cybersecurity, computer networks, and blockchain applications.',
    teachingAreas: ['Computer Networks', 'Information Security', 'Structured Programming Language'],
    researchInterests: ['Blockchain-based Electronic Voting', 'Intrusion Detection Systems', 'IoT Edge Security'],
    publications: [
      {
        id: 'pub-901',
        title: 'A Secure Decentralized E-Voting System for National Elections using Smart Contracts on Ethereum',
        authors: 'T. Rahman, S. Akhter',
        type: 'Conference',
        venue: 'International Conference on Cyber Security & Blockchain (ICCSB)',
        year: 2024,
        doi: '10.1109/ICCSB.2024.11',
        abstract: 'Designed and deployed a pilot smart contract addressing double-voting prevention, data immutability, and zero-knowledge privacy protocols for Bengali local council selections.',
        publisher: 'IEEE',
        citations: 3,
        link: '#'
      }
    ],
    jobExperiences: [
      {
        id: 'exp-901',
        role: 'Lecturer in Computer Science & Engineering',
        institution: 'Daffodil International University',
        duration: '2023 - Present'
      },
      {
        id: 'exp-902',
        role: 'Research Assistant',
        institution: 'Institute of Information Technology (IIT), Dhaka University',
        duration: '2021 - 2022'
      }
    ],
    trainingExperiences: [
      {
        id: 'trn-901',
        title: 'Ethical Hacking and Cyber Defense Boot Camp',
        organization: 'BGD e-GOV CIRT',
        year: 2023,
        duration: '5 Days'
      }
    ],
    awards: [
      {
        id: 'aw-901',
        title: 'Academic Gold Medal for Highest CGPA',
        organization: 'Dhaka University',
        year: 2022,
        category: 'National'
      }
    ],
    memberships: [
      'Associate Member, IEEE Computer Society'
    ]
  },
  {
    id: 't-10',
    name: 'Ms. Fahmida Chowdhury',
    avatar: 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&q=80&w=256',
    designation: 'Lecturer',
    administrativeRole: 'None',
    departmentId: 'dept-cse',
    email: 'fahmida.cse@daffodilvarsity.edu.bd',
    phone: '+8801700112233',
    office: 'Level 3, Academic Building 1, DIU Smart City',
    website: '',
    linkedin: '',
    googleScholar: '',
    bio: 'Ms. Fahmida Chowdhury joined DIU after completing her MSc in Data Science. She teaches introductory programming, web technologies, and human-computer interactions.',
    teachingAreas: ['Introductory Python Programming', 'Web Technologies', 'Human-Computer Interaction'],
    researchInterests: ['Accessibility for Blind Users on Bangladesh Websites', 'User Experience in LMS'],
    publications: [],
    jobExperiences: [
      {
        id: 'exp-1001',
        role: 'Lecturer',
        institution: 'Daffodil International University',
        duration: '2023 - Present'
      }
    ],
    trainingExperiences: [
      {
        id: 'trn-1001',
        title: 'University Teaching Methodologies',
        organization: 'DIU HRDI',
        year: 2023,
        duration: '3 Days'
      }
    ],
    awards: [],
    memberships: []
  },
  {
    id: 't-11',
    name: 'Dr. Md. Kabir Islam',
    avatar: 'https://images.unsplash.com/photo-1500048993953-d23a436266cf?auto=format&fit=crop&q=80&w=256',
    designation: 'Assistant Professor',
    administrativeRole: 'Advisor',
    departmentId: 'dept-swe',
    email: 'kabir.swe@daffodilvarsity.edu.bd',
    phone: '+8801711223344',
    office: 'Level 2, Academic Building 2, DIU Smart City',
    website: '',
    linkedin: '',
    googleScholar: '',
    bio: 'Dr. Md. Kabir Islam specializes in Software Architecture and Software Testing automation. He consults with multiple IT firms in Dhaka on QA quality control practices.',
    teachingAreas: ['Software Testing and Quality Assurance', 'Design Patterns', 'System Analysis & Design'],
    researchInterests: ['Automatic Test Case Generation', 'Defect Density Analysis in Agile'],
    publications: [
      {
        id: 'pub-1101',
        title: 'Evaluating Selenium vs. Playwright for Automated Regression Testing of Bengali E-Commerce Platforms',
        authors: 'M. K. Islam, R. Sultana',
        type: 'Conference',
        venue: 'International Conference on Software Engineering and Technology (ICSET)',
        year: 2023,
        doi: '10.1109/ICSET.2023.12',
        abstract: 'A comparative benchmark analyzing test execution speed, DOM element detection, and resource consumption when running extensive regression suites.',
        publisher: 'IEEE',
        citations: 5,
        link: '#'
      }
    ],
    jobExperiences: [
      {
        id: 'exp-1101',
        role: 'Assistant Professor of SWE',
        institution: 'Daffodil International University',
        duration: '2022 - Present'
      },
      {
        id: 'exp-1102',
        role: 'QA Architect',
        institution: 'Selise Rockin Software',
        duration: '2019 - 2022'
      }
    ],
    trainingExperiences: [
      {
        id: 'trn-1101',
        title: 'ISTQB Certified Tester training',
        organization: 'Bangladesh Software Testing Board',
        year: 2021,
        duration: '5 Days'
      }
    ],
    awards: [],
    memberships: [
      'ISTQB BD Affiliate',
      'IEEE Computer Society'
    ]
  },
  {
    id: 't-12',
    name: 'Ms. Farhana Jabeen',
    avatar: 'https://images.unsplash.com/photo-1567532939604-b6b5b0db2604?auto=format&fit=crop&q=80&w=256',
    designation: 'Senior Lecturer',
    administrativeRole: 'None',
    departmentId: 'dept-swe',
    email: 'farhana.swe@daffodilvarsity.edu.bd',
    phone: '+8801722334455',
    office: 'Level 2, Academic Building 2, DIU Smart City',
    website: '',
    linkedin: '',
    googleScholar: '',
    bio: 'Ms. Farhana Jabeen coordinates the student projects and thesis presentations at SWE department. She does research in software project estimation metrics.',
    teachingAreas: ['Software Metrics', 'Object Oriented Design', 'Human-Computer Interaction'],
    researchInterests: ['COCOMO Model Tuning for Bangladeshi Small Scale Software Firms'],
    publications: [],
    jobExperiences: [
      {
        id: 'exp-1201',
        role: 'Senior Lecturer',
        institution: 'Daffodil International University',
        duration: '2021 - Present'
      }
    ],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-13',
    name: 'Mr. Shafiul Alam',
    avatar: 'https://images.unsplash.com/photo-1506803682981-6e718a9dd3ee?auto=format&fit=crop&q=80&w=256',
    designation: 'Lecturer',
    administrativeRole: 'None',
    departmentId: 'dept-swe',
    email: 'shafiul.swe@daffodilvarsity.edu.bd',
    phone: '+8801733445566',
    office: 'Level 2, Academic Building 2, DIU Smart City',
    website: '',
    linkedin: '',
    googleScholar: '',
    bio: 'Mr. Shafiul Alam teaches introductory computer networks and software construction algorithms. He holds a Bachelor of Software Engineering from DIU.',
    teachingAreas: ['Computer Architecture', 'Structured Programming', 'Web Engineering'],
    researchInterests: ['Serverless Computing Efficiencies', 'Microservice Architectures'],
    publications: [],
    jobExperiences: [
      {
        id: 'exp-1301',
        role: 'Lecturer',
        institution: 'Daffodil International University',
        duration: '2023 - Present'
      }
    ],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-14',
    name: 'Prof. Dr. Md. Bellal Hossain',
    avatar: 'https://images.unsplash.com/photo-1537368910025-700350fe46c7?auto=format&fit=crop&q=80&w=256',
    designation: 'Professor',
    administrativeRole: 'Dean',
    departmentId: 'dept-nfe',
    email: 'bellal.nfe@daffodilvarsity.edu.bd',
    phone: '+8801711223355',
    office: 'Level 4, Science Complex, DIU Smart City',
    website: '',
    linkedin: 'https://linkedin.com/in/md-bellal-hossain',
    googleScholar: 'https://scholar.google.com/citations?user=bellal',
    bio: 'Prof. Dr. Md. Bellal Hossain is an eminent academic and clinical nutrition specialist in Bangladesh. He leads the Faculty of Health and Life Sciences (FHLS), initiating cross-disciplinary medical research collaborations.',
    teachingAreas: ['Food Biotechnology', 'Human Nutrition', 'Food Security Policies', 'Functional Foods'],
    researchInterests: ['Nutritional Epidemiology', 'Antioxidants in Local Crops', 'Food Product Formulation', 'Public Safety Auditing'],
    publications: [
      {
        id: 'pub-1401',
        title: 'Nutritional Composition and Therapeutic Potentials of Local Indigenous Medicinal Plants in Bangladesh',
        authors: 'M. B. Hossain, S. Sultana, M. A. Karim',
        type: 'Journal',
        venue: 'International Journal of Food Science & Technology',
        year: 2024,
        doi: '10.1111/ijfs.14022',
        abstract: 'This research details physical composition analysis, mineral distribution, and biochemical extraction values for seven local plants, highlighting significant therapeutic and antioxidant qualities.',
        publisher: 'Wiley Blackwell',
        citations: 32,
        link: '#'
      }
    ],
    jobExperiences: [
      {
        id: 'exp-1401',
        role: 'Dean of Faculty of Health & Life Sciences',
        institution: 'Daffodil International University',
        duration: '2021 - Present',
        description: 'Directing the pharmaceutical and nutritional development strategies, introducing modern clinical simulation labs, and organizing national dietary summits.'
      }
    ],
    trainingExperiences: [
      {
        id: 'trn-1401',
        title: 'Modern Biotechnology and Food Quality Control Certification',
        organization: 'National Institute of Nutrition (NIN), India',
        year: 2019,
        duration: '2 Weeks'
      }
    ],
    awards: [
      {
        id: 'aw-1401',
        title: 'Outstanding Nutritionist of the Year',
        organization: 'Bangladesh Nutritional Association',
        year: 2023,
        category: 'Research'
      }
    ],
    memberships: [
      'Fellow, Nutrition Society of Bangladesh',
      'Member, International Union of Food Science and Technology (IUFoST)'
    ]
  },
  {
    id: 't-15',
    name: 'Prof. Dr. Muniruddin Ahmed',
    avatar: 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=256',
    designation: 'Professor',
    administrativeRole: 'Head of Department',
    departmentId: 'dept-pharm',
    email: 'muniruddin.phr@daffodilvarsity.edu.bd',
    phone: '+8801722334466',
    office: 'Level 3, Health Block, DIU Smart City',
    website: '',
    linkedin: '',
    googleScholar: 'https://scholar.google.com/citations?user=muniruddin',
    bio: 'Prof. Dr. Muniruddin Ahmed is a prominent pharmacologist with extensive research records in drug discovery, clinical screening, and herbal drug standardization. He coordinates the Pharmacy Department\'s academic boards.',
    teachingAreas: ['Advanced Pharmacology', 'Biopharmaceutics', 'Clinical Trial Methodologies', 'Medicinal Chemistry'],
    researchInterests: ['Natural Products Isolation', 'In Vivo Toxicology Screenings', 'Nano-drug Carriers'],
    publications: [
      {
        id: 'pub-1501',
        title: 'Synthesis and In-Vitro Evaluation of Novel Curcumin Derivatives with Enhanced Anti-inflammatory Actions',
        authors: 'M. Ahmed, F. J. Preeti, R. S. Roy',
        type: 'Journal',
        venue: 'Journal of Pharmacy and Pharmacology',
        year: 2023,
        doi: '10.1093/jpp/rgad101',
        abstract: 'An investigation outlining synthesized lipophilic curcumin formulations that increase cellular absorption, delivering 3x higher efficacy compared to natural curcumin extracts.',
        publisher: 'Oxford University Press',
        citations: 18,
        link: '#'
      }
    ],
    jobExperiences: [
      {
        id: 'exp-1501',
        role: 'Professor and Head, Department of Pharmacy',
        institution: 'Daffodil International University',
        duration: '2020 - Present',
        description: 'Managing academic accreditation audits, coordinating research funds, and guiding postgraduate thesis completions.'
      }
    ],
    trainingExperiences: [
      {
        id: 'trn-1501',
        title: 'Accreditation Standards for Pharmacy Education (ACPE)',
        organization: 'International Pharmaceutical Federation (FIP)',
        year: 2022,
        duration: '5 Days'
      }
    ],
    awards: [],
    memberships: [
      'Member, Bangladesh Pharmaceutical Society (BPS)',
      'Member, American Association of Pharmaceutical Scientists (AAPS)'
    ]
  },
  {
    id: 't-16',
    name: 'Prof. Dr. Shah Md. Keramat Ali',
    avatar: 'https://images.unsplash.com/photo-1512486130939-2c4f79935e4f?auto=format&fit=crop&q=80&w=256',
    designation: 'Professor',
    administrativeRole: 'Head of Department',
    departmentId: 'dept-ph',
    email: 'keramat.ph@daffodilvarsity.edu.bd',
    phone: '+8801733445577',
    office: 'Level 3, Health Block, DIU Smart City',
    website: '',
    linkedin: '',
    googleScholar: '',
    bio: 'Prof. Dr. Shah Md. Keramat Ali is a pioneer in public health advocacy and community preventive healthcare in Bangladesh. He directs the Department of Public Health with a strong research footprint in rural community medicine.',
    teachingAreas: ['Epidemiology of Communicable Diseases', 'Global Healthcare Systems', 'Preventive Medicine'],
    researchInterests: ['Community Sanitation Interventions', 'Maternal and Child Nutrition', 'Disease Burden Analysis'],
    publications: [],
    jobExperiences: [
      {
        id: 'exp-1601',
        role: 'Professor and Head, Department of Public Health',
        institution: 'Daffodil International University',
        duration: '2022 - Present',
        description: 'Steering the MPH programs, orchestrating rural healthcare diagnostic camps, and leading research on local disease outbreaks.'
      }
    ],
    trainingExperiences: [],
    awards: [
      {
        id: 'aw-1601',
        title: 'National Public Health Lifetime Achievement Award',
        organization: 'Ministry of Health & Family Welfare, Bangladesh',
        year: 2024,
        category: 'National'
      }
    ],
    memberships: [
      'President, Public Health Association of Bangladesh',
      'Member, World Federation of Public Health Associations (WFPHA)'
    ]
  },
  {
    id: 't-17',
    name: 'Dr. Md. Kabirul Islam',
    avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&q=80&w=256',
    designation: 'Associate Professor',
    departmentId: 'dept-cse',
    email: 'kabirul.cse@daffodilvarsity.edu.bd',
    phone: '+8801712345617',
    office: 'Level 5, Tower-B, DIU Smart City',
    bio: 'Dr. Md. Kabirul Islam is an Associate Professor specializing in Human-Computer Interaction and Assistive Technologies. He has published numerous research articles on mobile health systems and accessibility solutions.',
    teachingAreas: ['Human-Computer Interaction', 'User Interface Design', 'Software Project Management'],
    researchInterests: ['Accessibility', 'Assistive Tech', 'Mobile Health Informatics'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-18',
    name: 'Ms. Farhana Sarker',
    avatar: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&q=80&w=256',
    designation: 'Assistant Professor',
    departmentId: 'dept-cse',
    email: 'farhana.cse@daffodilvarsity.edu.bd',
    phone: '+8801712345618',
    office: 'Level 5, Tower-A, DIU Smart City',
    bio: 'Ms. Farhana Sarker is an Assistant Professor with a keen interest in Artificial Intelligence and Machine Learning applications in healthcare. She has led several research initiatives focused on breast cancer detection models.',
    teachingAreas: ['Artificial Intelligence', 'Discrete Mathematics', 'Programming Language C'],
    researchInterests: ['Deep Learning', 'Medical Imaging Research', 'Computer Vision'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-19',
    name: 'Mr. Tanvir Rahman',
    avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&q=80&w=256',
    designation: 'Senior Lecturer',
    departmentId: 'dept-cse',
    email: 'tanvir.cse@daffodilvarsity.edu.bd',
    phone: '+8801712345619',
    office: 'Level 6, Academic Building 2, DIU Smart City',
    bio: 'Mr. Tanvir Rahman has been teaching at DIU for 5 years. His research areas include blockchain architectures, secure consensus algorithms, and distributed computing networks.',
    teachingAreas: ['Database Management Systems', 'Data Structures', 'Information Security'],
    researchInterests: ['Blockchain Systems', 'Cryptographic Protocols', 'Peer-to-Peer Networks'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-20',
    name: 'Dr. S. M. Aminul Haque',
    avatar: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&q=80&w=256',
    designation: 'Associate Professor',
    departmentId: 'dept-cse',
    email: 'aminul.cse@daffodilvarsity.edu.bd',
    phone: '+8801712345620',
    office: 'Level 4, Tower-A, DIU Smart City',
    bio: 'Dr. Aminul Haque research focus lies at the intersection of wireless networks, sensor designs, and IoT communication protocols. He frequently advises undergraduate research teams on hardware integrations.',
    teachingAreas: ['Computer Networks', 'Wireless Communications', 'Microprocessors & Interfacing'],
    researchInterests: ['Internet of Things (IoT)', 'Wireless Sensor Networks', 'Ad-hoc Routing Protocols'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-21',
    name: 'Ms. Tasneem Rahman',
    avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=256',
    designation: 'Lecturer',
    departmentId: 'dept-cse',
    email: 'tasneem.cse@daffodilvarsity.edu.bd',
    phone: '+8801712345621',
    office: 'Level 6, Tower-A, DIU Smart City',
    bio: 'Ms. Tasneem is a passionate educator specializing in computational mathematics and graph theoretic modeling. She is actively involved in organizing programming contests inside the CSE department.',
    teachingAreas: ['Structured Programming', 'Graph Theory', 'Digital Logic Design'],
    researchInterests: ['Algorithmic Complexity', 'Network Optimization', 'Educational Technology'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-22',
    name: 'Mr. Md. Rashedul Islam',
    avatar: 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&q=80&w=256',
    designation: 'Senior Lecturer',
    departmentId: 'dept-cse',
    email: 'rashedul.cse@daffodilvarsity.edu.bd',
    phone: '+8801712345622',
    office: 'Level 5, Academic Building 1, DIU Smart City',
    bio: 'Mr. Rashedul Islam conducts active research in Cloud Computing, virtualizations, and software containers. He brings several years of industry experience as a system engineer prior to his academic career.',
    teachingAreas: ['Operating Systems', 'System Administration', 'Cloud Computing Architecture'],
    researchInterests: ['Serverless Computing', 'Containerization Security', 'Distributed Storage Systems'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-23',
    name: 'Ms. Sabrina Alam',
    avatar: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=256',
    designation: 'Lecturer',
    departmentId: 'dept-cse',
    email: 'sabrina.cse@daffodilvarsity.edu.bd',
    phone: '+8801712345623',
    office: 'Level 6, Tower-B, DIU Smart City',
    bio: 'Ms. Sabrina Alam specializes in Big Data pipelines and advanced analytics systems. Her research explores automated metadata tagging and real-time event streaming systems.',
    teachingAreas: ['Object Oriented Programming', 'Database Systems', 'Big Data Technologies'],
    researchInterests: ['Streaming Analytics', 'Data Warehousing', 'Feature Engineering'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-24',
    name: 'Mr. Fahad Faisal',
    avatar: 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&q=80&w=256',
    designation: 'Lecturer',
    departmentId: 'dept-cse',
    email: 'fahad.cse@daffodilvarsity.edu.bd',
    phone: '+8801712345624',
    office: 'Level 4, Academic Building 2, DIU Smart City',
    bio: 'Mr. Fahad Faisal is an active mentor for software builders. His interests span high-performance compilers, syntax parsers, and system performance diagnostics.',
    teachingAreas: ['Compiler Design', 'Formal Languages & Automata', 'Assembly Language'],
    researchInterests: ['Code Optimization', 'Static Program Analysis', 'Virtual Machines'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-25',
    name: 'Dr. Sheak Rashed Haque',
    avatar: 'https://images.unsplash.com/photo-1537368910025-700350fe46c7?auto=format&fit=crop&q=80&w=256',
    designation: 'Assistant Professor',
    departmentId: 'dept-cse',
    email: 'rashed.cse@daffodilvarsity.edu.bd',
    phone: '+8801712345625',
    office: 'Level 5, Tower-A, DIU Smart City',
    bio: 'Dr. Sheak Rashed Haque research spans high-dimensional bioinformatics, genomic sequencing algorithms, and computer-aided drug design systems using machine learning.',
    teachingAreas: ['Bioinformatics', 'Design & Analysis of Algorithms', 'Machine Learning Theory'],
    researchInterests: ['Computational Biology', 'Sequence Alignment Networks', 'Graph Neural Networks'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-26',
    name: 'Mr. Md. Redwan Ahmed',
    avatar: 'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?auto=format&fit=crop&q=80&w=256',
    designation: 'Lecturer',
    departmentId: 'dept-cse',
    email: 'redwan.cse@daffodilvarsity.edu.bd',
    phone: '+8801712345626',
    office: 'Level 6, Tower-A, DIU Smart City',
    bio: 'Mr. Redwan Ahmed works in computer graphics, 3D rendering engines, and real-time visualization systems. He assists in running the computing graphics laboratory classes.',
    teachingAreas: ['Computer Graphics', 'Structured Programming', 'Web Engineering'],
    researchInterests: ['Ray Tracing', 'Virtual Reality Interactions', 'WebGL Architectures'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-27',
    name: 'Ms. Nazia Nishat',
    avatar: 'https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&q=80&w=256',
    designation: 'Lecturer',
    departmentId: 'dept-cse',
    email: 'nazia.cse@daffodilvarsity.edu.bd',
    phone: '+8801712345627',
    office: 'Level 5, Tower-B, DIU Smart City',
    bio: 'Ms. Nazia is an AI researcher focusing on Natural Language Processing (NLP), sentimental analysis, and speech-to-text translations for regional dialects.',
    teachingAreas: ['Artificial Intelligence', 'Data Structures', 'Introduction to Software System'],
    researchInterests: ['Natural Language Processing', 'Regional Voice Synthesis', 'Topic Modeling'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-28',
    name: 'Dr. Md. Zahidul Islam',
    avatar: 'https://images.unsplash.com/photo-1489980508314-941910ded1f4?auto=format&fit=crop&q=80&w=256',
    designation: 'Associate Professor',
    departmentId: 'dept-cse',
    email: 'zahidul.cse@daffodilvarsity.edu.bd',
    phone: '+8801712345628',
    office: 'Level 4, Tower-B, DIU Smart City',
    bio: 'Dr. Md. Zahidul Islam has over 12 years of experience in cryptographic security and blockchain-integrated networks. He is the author of multiple cybersecurity books.',
    teachingAreas: ['Cryptography & Network Security', 'Information Assurance', 'Discrete Mathematics'],
    researchInterests: ['Elliptic Curve Cryptography', 'Smart Contract Auditing', 'Privacy-Preserving Tech'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-29',
    name: 'Mr. Kazi Jahid Hasan',
    avatar: 'https://images.unsplash.com/photo-1542909168-82c3e7fdca5c?auto=format&fit=crop&q=80&w=256',
    designation: 'Senior Lecturer',
    departmentId: 'dept-cse',
    email: 'jahid.cse@daffodilvarsity.edu.bd',
    phone: '+8801712345629',
    office: 'Level 6, Academic Building 1, DIU Smart City',
    bio: 'Mr. Kazi Jahid Hasan is a dedicated teacher of design algorithms and competitive coding. He guides student chapters in international ACM ICPC competitions.',
    teachingAreas: ['Analysis & Design of Algorithms', 'Numerical Methods', 'Competitive Programming'],
    researchInterests: ['Computational Complexity', 'Heuristic Searches', 'Metaheuristic Algorithmic Designs'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-30',
    name: 'Ms. Jarin Tasnim',
    avatar: 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&q=80&w=256',
    designation: 'Lecturer',
    departmentId: 'dept-cse',
    email: 'jarin.cse@daffodilvarsity.edu.bd',
    phone: '+8801712345630',
    office: 'Level 5, Tower-A, DIU Smart City',
    bio: 'Ms. Jarin research targets neural text-to-speech models and neural machine translation pipelines for Bengali-English corpus. She has a high passion for educational technologies.',
    teachingAreas: ['Computer Architecture', 'Structured Programming', 'Web Systems'],
    researchInterests: ['Machine Translation', 'Neural Conversational Bots', 'Multi-modal AI'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-31',
    name: 'Mr. Safayet Ullah',
    avatar: 'https://images.unsplash.com/photo-1566492031773-4f4e44671857?auto=format&fit=crop&q=80&w=256',
    designation: 'Lecturer',
    departmentId: 'dept-cse',
    email: 'safayet.cse@daffodilvarsity.edu.bd',
    phone: '+8801712345631',
    office: 'Level 4, Academic Building 2, DIU Smart City',
    bio: 'Mr. Safayet conducts research in advanced image restoration techniques and computer-aided diagnosis using Generative Adversarial Networks.',
    teachingAreas: ['Digital Image Processing', 'Software Engineering', 'Discrete Systems'],
    researchInterests: ['GAN Models', 'Image Restoration', 'Explainable AI Models'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-32',
    name: 'Dr. S. S. M. Motiur Rahman',
    avatar: 'https://images.unsplash.com/photo-1500048993953-d23a436266cf?auto=format&fit=crop&q=80&w=256',
    designation: 'Professor',
    departmentId: 'dept-cse',
    email: 'motiur.cse@daffodilvarsity.edu.bd',
    phone: '+8801712345632',
    office: 'Level 4, Tower-B, DIU Smart City',
    bio: 'Dr. Motiur Rahman has over 18 years of academic standing in software fault tolerances, extreme programming practices, and system modeling methodologies.',
    teachingAreas: ['Advanced Software Engineering', 'Software Architecture', 'System Analysis & Design'],
    researchInterests: ['Fault Tolerance Systems', 'Software Reliability Engineering', 'Agile Operations'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-33',
    name: 'Ms. Sharmin Akter',
    avatar: 'https://images.unsplash.com/photo-1567532939604-b6b5b0db2604?auto=format&fit=crop&q=80&w=256',
    designation: 'Senior Lecturer',
    departmentId: 'dept-cse',
    email: 'sharmin.cse@daffodilvarsity.edu.bd',
    phone: '+8801712345633',
    office: 'Level 5, Academic Building 1, DIU Smart City',
    bio: 'Ms. Sharmin Akter works in educational technologies, tutoring systems, and virtual class orchestrations. She is heavily involved in student advisory councils.',
    teachingAreas: ['Structured Programming', 'Object Oriented Programming', 'Database Systems'],
    researchInterests: ['E-learning Architectures', 'Gamified Pedagogy', 'Interactive Learning Modules'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-34',
    name: 'Mr. Amit Kumar Saha',
    avatar: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=256',
    designation: 'Lecturer',
    departmentId: 'dept-cse',
    email: 'amit.cse@daffodilvarsity.edu.bd',
    phone: '+8801712345634',
    office: 'Level 6, Tower-B, DIU Smart City',
    bio: 'Mr. Amit Saha researches automated code syntax correction systems using neural sequence architectures. He serves as an executive member for software project exhibitions.',
    teachingAreas: ['Web Engineering', 'Programming Language C++', 'Data Structures'],
    researchInterests: ['Automatic Bug Patching', 'Source Code Analysis Models', 'Web Performance'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-35',
    name: 'Dr. Md. Ismail Jabiullah',
    avatar: 'https://images.unsplash.com/photo-1544717305-2782549b5136?auto=format&fit=crop&q=80&w=256',
    designation: 'Professor',
    departmentId: 'dept-cse',
    email: 'ismail.cse@daffodilvarsity.edu.bd',
    phone: '+8801712345635',
    office: 'Level 3, Tower-A, DIU Smart City',
    bio: 'Prof. Dr. Md. Ismail Jabiullah has extensive academic records in cryptology, cellular security protocols, and advanced algorithms. He leads the core research board for the FSIT faculty.',
    teachingAreas: ['Information Security', 'Network Security', 'Advanced Cryptography', 'Parallel Processing'],
    researchInterests: ['Cybersecurity Infrastructures', 'Cloud Resource Schemas', 'High Performance Computing'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-36',
    name: 'Ms. Sadia Rahman',
    avatar: 'https://images.unsplash.com/photo-1594744803329-e58b31de215f?auto=format&fit=crop&q=80&w=256',
    designation: 'Assistant Professor',
    departmentId: 'dept-swe',
    email: 'sadia.swe@daffodilvarsity.edu.bd',
    phone: '+8801712345636',
    office: 'Level 5, SWE Wing, DIU Smart City',
    bio: 'Ms. Sadia Rahman focuses on agile methodology optimizations, DevOps scaling systems, and automated QA suites.',
    teachingAreas: ['Software Testing & Quality Assurance', 'Agile Software Development', 'System Requirements Engineering'],
    researchInterests: ['DevOps Workflows', 'Continuous Integration Tests', 'Software Engineering Metrics'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-37',
    name: 'Mr. S. M. Hasan Mahmud',
    avatar: 'https://images.unsplash.com/photo-1501196354995-cbb51c65aaea?auto=format&fit=crop&q=80&w=256',
    designation: 'Senior Lecturer',
    departmentId: 'dept-swe',
    email: 'hasan.swe@daffodilvarsity.edu.bd',
    phone: '+8801712345637',
    office: 'Level 5, SWE Wing, DIU Smart City',
    bio: 'Mr. Hasan Mahmud specializes in microservice patterns and distributed system performance evaluations. He guides multiple software development capstone projects.',
    teachingAreas: ['Design Patterns', 'Object Oriented Design', 'Microservices Engineering'],
    researchInterests: ['Distributed Software Architectures', 'Refactoring Methods', 'Containerized APIs'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-38',
    name: 'Ms. Afsana Begum',
    avatar: 'https://images.unsplash.com/photo-1531123897727-8f129e1688ce?auto=format&fit=crop&q=80&w=256',
    designation: 'Lecturer',
    departmentId: 'dept-swe',
    email: 'afsana.swe@daffodilvarsity.edu.bd',
    phone: '+8801712345638',
    office: 'Level 5, SWE Wing, DIU Smart City',
    bio: 'Ms. Afsana Begum research focuses on mobile usability testing, cross-platform app performance, and human interaction metrics.',
    teachingAreas: ['Mobile Application Development', 'User Experience Testing', 'Software Construction'],
    researchInterests: ['Usability Engineering', 'Flutter Performance Schemas', 'Cognitive UI Principles'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-39',
    name: 'Mr. Touhid Bhuiyan',
    avatar: 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&q=80&w=256',
    designation: 'Professor',
    departmentId: 'dept-swe',
    email: 'touhid.swe@daffodilvarsity.edu.bd',
    phone: '+8801712345639',
    office: 'Level 4, SWE Wing, DIU Smart City',
    bio: 'Prof. Touhid Bhuiyan is an eminent academic in security schemas and trust models inside software services. He has chaired several international software engineering conferences.',
    teachingAreas: ['Software Security', 'Advanced Database Systems', 'Trust Computing Frameworks'],
    researchInterests: ['Trust Modeling', 'Enterprise Security Policies', 'Intelligent Security Systems'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-40',
    name: 'Dr. Md. Asif Ur Rahman',
    avatar: 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?auto=format&fit=crop&q=80&w=256',
    designation: 'Associate Professor',
    departmentId: 'dept-swe',
    email: 'asif.swe@daffodilvarsity.edu.bd',
    phone: '+8801712345640',
    office: 'Level 5, SWE Wing, DIU Smart City',
    bio: 'Dr. Md. Asif Ur Rahman conducts active research on Edge Computing optimization algorithms and swarm intelligence paradigms.',
    teachingAreas: ['Enterprise Application Architecture', 'Distributed Systems', 'Advanced Algorithms'],
    researchInterests: ['Edge Computing Optimization', 'Swarm Networks', 'Cloud Security'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-41',
    name: 'Mr. Mushfiqur Rahman',
    avatar: 'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&q=80&w=256',
    designation: 'Lecturer',
    departmentId: 'dept-swe',
    email: 'mushfiqur.swe@daffodilvarsity.edu.bd',
    phone: '+8801712345641',
    office: 'Level 5, SWE Wing, DIU Smart City',
    bio: 'Mr. Mushfiqur Rahman is a lecturer with primary interests in model-driven engineering, automated code generator optimizations, and UML diagram parsers.',
    teachingAreas: ['Software Engineering Tools', 'System Modeling', 'Structured Programming'],
    researchInterests: ['Model-driven Engineering', 'UML Code Generators', 'Tool Optimizations'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-42',
    name: 'Ms. Humaira Salma',
    avatar: 'https://images.unsplash.com/photo-1558203728-00f45181dd14?auto=format&fit=crop&q=80&w=256',
    designation: 'Senior Lecturer',
    departmentId: 'dept-swe',
    email: 'humaira.swe@daffodilvarsity.edu.bd',
    phone: '+8801712345642',
    office: 'Level 5, SWE Wing, DIU Smart City',
    bio: 'Ms. Humaira Salma specializes in continuous testing pipelines, software release architectures, and extreme software patterns.',
    teachingAreas: ['Software Project Management', 'Advanced Testing Methods', 'Software Quality Standards'],
    researchInterests: ['Automated Release Management', 'Risk-driven Testing', 'Agile Product Metrics'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-43',
    name: 'Mr. Md. Shamimur Rahman',
    avatar: 'https://images.unsplash.com/photo-1508214751196-bcfd4ca60f91?auto=format&fit=crop&q=80&w=256',
    designation: 'Assistant Professor',
    departmentId: 'dept-eee',
    email: 'shamimur.eee@daffodilvarsity.edu.bd',
    phone: '+8801712345643',
    office: 'Level 3, Engineering Block, DIU Smart City',
    bio: 'Mr. Shamimur Rahman works in renewable energy microgrids, high-voltage battery designs, and rural power transmission solutions.',
    teachingAreas: ['Electrical Circuits', 'Power System Analysis', 'Renewable Energy Systems'],
    researchInterests: ['Solar Microgrids', 'Lithium Battery Aging Models', 'Smart Transmission Lines'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-44',
    name: 'Ms. Farzana Akter',
    avatar: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&q=80&w=256',
    designation: 'Lecturer',
    departmentId: 'dept-eee',
    email: 'farzana.eee@daffodilvarsity.edu.bd',
    phone: '+8801712345644',
    office: 'Level 3, Engineering Block, DIU Smart City',
    bio: 'Ms. Farzana conducts active research in smart sensor layouts, semiconductor material designs, and low-power microcontrollers.',
    teachingAreas: ['Electronics I', 'Digital Logic Systems', 'Semiconductor Devices'],
    researchInterests: ['Low-power Sensor Layouts', 'Silicon-Germanium Semiconductors', 'Flexible Circuit Materials'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-45',
    name: 'Mr. Md. Maruf Ahmed',
    avatar: 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&q=80&w=256',
    designation: 'Lecturer',
    departmentId: 'dept-eee',
    email: 'maruf.eee@daffodilvarsity.edu.bd',
    phone: '+8801712345645',
    office: 'Level 3, Engineering Block, DIU Smart City',
    bio: 'Mr. Maruf Ahmed works on automated robotic control loops, drone stabilizing mechanics, and embedded computer systems.',
    teachingAreas: ['Signals & Systems', 'Microprocessors & Interfacing', 'Control Systems Engineering'],
    researchInterests: ['Robot PID Controllers', 'Quadcopter Navigation Models', 'Embedded Linux Systems'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-46',
    name: 'Dr. Md. Abdul Jalil',
    avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&q=80&w=256',
    designation: 'Professor',
    departmentId: 'dept-law',
    email: 'abduljalil.law@daffodilvarsity.edu.bd',
    phone: '+8801712345646',
    office: 'Level 2, FHSS Block, DIU Smart City',
    bio: 'Prof. Dr. Abdul Jalil has over 20 years of research standing in constitutional laws, human rights frameworks, and international labor standards.',
    teachingAreas: ['Constitutional Law', 'International Law', 'Human Rights Jurisprudence'],
    researchInterests: ['Labor Safety Frameworks', 'Environmental Legal Protections', 'Digital Privacy Legal Systems'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-47',
    name: 'Ms. Alifa Khatun',
    avatar: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=256',
    designation: 'Senior Lecturer',
    departmentId: 'dept-law',
    email: 'alifa.law@daffodilvarsity.edu.bd',
    phone: '+8801712345647',
    office: 'Level 2, FHSS Block, DIU Smart City',
    bio: 'Ms. Alifa Khatun focuses on family jurisprudence, corporate arbitration policies, and mediation workflows inside civil laws.',
    teachingAreas: ['Civil Procedure Code', 'Company Law', 'Alternative Dispute Resolution'],
    researchInterests: ['Commercial Arbitration Workflows', 'Gender Equality Legal Rights', 'Intellectual Property Laws'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-48',
    name: 'Mr. Md. Raju Ahmed',
    avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&q=80&w=256',
    designation: 'Assistant Professor',
    departmentId: 'dept-ba',
    email: 'raju.ba@daffodilvarsity.edu.bd',
    phone: '+8801712345648',
    office: 'Level 3, Business Block, DIU Smart City',
    bio: 'Mr. Raju Ahmed teaches corporate finance and global market analytics. He consults several regional organizations on risk assessments.',
    teachingAreas: ['Financial Management', 'Investment Portfolio Analytics', 'Corporate Finance'],
    researchInterests: ['SME Risk Mitigations', 'Islamic Banking Schemes', 'Emerging Capital Market Models'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-49',
    name: 'Ms. Mehnaz Tabassum',
    avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=256',
    designation: 'Lecturer',
    departmentId: 'dept-ba',
    email: 'mehnaz.ba@daffodilvarsity.edu.bd',
    phone: '+8801712345649',
    office: 'Level 3, Business Block, DIU Smart City',
    bio: 'Ms. Mehnaz Tabassum is specialized in consumer purchase habits, digital branding models, and social media marketing research.',
    teachingAreas: ['Marketing Management', 'Consumer Behavior', 'Services Marketing'],
    researchInterests: ['E-commerce Trust Factors', 'Neuromarketing Trends', 'Brand Loyalty Metrics'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-50',
    name: 'Dr. Md. Milan Khan',
    avatar: 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&q=80&w=256',
    designation: 'Associate Professor',
    departmentId: 'dept-pharm',
    email: 'milan.phr@daffodilvarsity.edu.bd',
    phone: '+8801712345650',
    office: 'Level 3, Health Block, DIU Smart City',
    bio: 'Dr. Milan Khan teaches drug synthesis and molecular pharmacology. His research centers around discovering bioactive agents from forest reserves.',
    teachingAreas: ['Advanced Organic Chemistry', 'Drug Design Principles', 'Toxicology Systems'],
    researchInterests: ['Bioactive Natural Agents', 'Cancer Chemotherapy Inhibitors', 'Phytochemistry'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
  {
    id: 't-51',
    name: 'Ms. Shahnaz Parvin',
    avatar: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&q=80&w=256',
    designation: 'Senior Lecturer',
    departmentId: 'dept-nfe',
    email: 'shahnaz.nfe@daffodilvarsity.edu.bd',
    phone: '+8801712345651',
    office: 'Level 4, Health Block, DIU Smart City',
    bio: 'Ms. Shahnaz Parvin specializes in pediatric nutrition, food microbiology safety guidelines, and clinical diet formulas.',
    teachingAreas: ['Human Nutrition', 'Food Microbiology', 'Pediatric Dietetics'],
    researchInterests: ['Foodborne Pathogen Inhibitors', 'School Meal Caloric Distributions', 'Local Dietary Deficiencies'],
    publications: [],
    jobExperiences: [],
    trainingExperiences: [],
    awards: [],
    memberships: []
  },
];

// Helper function to generate 199 extra high-quality academic profiles to meet the 250 teacher requirement
function generateExtraTeachers(): Teacher[] {
  const departments = [
    'dept-cse', 'dept-swe', 'dept-mct', 'dept-cis', 'dept-itm', 'dept-esdm', 'dept-pess',
    'dept-ba', 'dept-thm', 'dept-re', 'dept-ie', 'dept-eee', 'dept-te', 'dept-ce', 'dept-arch',
    'dept-eng', 'dept-jmc', 'dept-law', 'dept-ds', 'dept-pharm', 'dept-ph', 'dept-nfe'
  ];
  
  const designativeRoles: ('Professor' | 'Associate Professor' | 'Assistant Professor' | 'Senior Lecturer' | 'Lecturer')[] = [
    'Professor', 'Associate Professor', 'Assistant Professor', 'Senior Lecturer', 'Lecturer'
  ];
  
  const firstNames = [
    'Mohammad', 'Md.', 'S. M.', 'Farhana', 'Tareq', 'Tanvir', 'Sharmin', 'Anisur', 'Kazi', 'Amit',
    'Sadia', 'Rafiqul', 'Shafiul', 'Nusrat', 'Mahbub', 'Rashedul', 'Tasnim', 'Shamsur', 'Mahfuzur',
    'Humaira', 'Abdur', 'Asif', 'Arif', 'Laila', 'Rashed', 'Sajid', 'Fatima', 'Rezaul', 'Kamrul'
  ];
  
  const lastNames = [
    'Rahman', 'Islam', 'Hasan', 'Ahmed', 'Khan', 'Chowdhury', 'Karim', 'Sultana', 'Parvin', 'Alam',
    'Ali', 'Uddin', 'Sarker', 'Bhuiyan', 'Yasmin', 'Jahan', 'Akter', 'Talukder', 'Haque', 'Mia', 'Munshi'
  ];

  const teachingPool = {
    tech: ['Data Structures', 'Database Systems', 'Algorithms', 'Software Engineering', 'Artificial Intelligence', 'Web Development', 'Computer Networks', 'Cyber Security'],
    business: ['Principles of Management', 'Financial Accounting', 'Marketing Strategies', 'Microeconomics', 'Business Communication', 'Corporate Finance', 'Strategic HRM'],
    humanities: ['English Composition', 'Business English', 'Sociology of Development', 'Media Ethics', 'Public Speaking', 'Constitutional Law', 'Legal System of Bangladesh'],
    science: ['Clinical Pharmacy', 'Biochemistry', 'Epidemiology', 'Environmental Pollution', 'Pediatric Nutrition', 'Human Anatomy', 'Pathology']
  };

  const researchPool = {
    tech: ['Machine Learning Systems', 'Blockchain Architectures', 'Edge Computing Protocols', 'Natural Language Understanding', 'Human-Computer Interaction'],
    business: ['SME Sustainability', 'Fintech Disruptions', 'Consumer Neuromarketing', 'Supply Chain Risk Management', 'Green Investment Portfolios'],
    humanities: ['Digital Media Audiences', 'Linguistic Adaptations', 'Human Rights Legislation', 'Sustainable Policy Frameworks', 'Development Sociology'],
    science: ['Phytochemical Syntheses', 'Community Health Screenings', 'Eco-toxicology Resilience', 'Clinical Formulation Stability']
  };

  const extra: Teacher[] = [];
  
  // Starting index 52 to 250 (199 teachers)
  for (let i = 52; i <= 250; i++) {
    const fName = firstNames[i % firstNames.length];
    const lName = lastNames[i % lastNames.length];
    const name = `${fName} ${lName}`;
    const cleanName = name.replace(/^(Dr\.|Prof\.|Mr\.|Ms\.|Mrs\.)\s+/i, '').toLowerCase().replace(/[^a-z]/g, '');
    const emailName = cleanName || `teacher${i}`;
    
    // Choose department and designation
    const departmentId = departments[i % departments.length];
    const designation = designativeRoles[(i % 100 < 10) ? 0 : (i % 100 < 25) ? 1 : (i % 100 < 50) ? 2 : (i % 100 < 75) ? 3 : 4];
    
    // Assign proper pool
    let poolKey: 'tech' | 'business' | 'humanities' | 'science' = 'tech';
    if (['dept-cse', 'dept-swe', 'dept-mct', 'dept-cis', 'dept-itm', 'dept-eee', 'dept-te', 'dept-ce', 'dept-arch'].includes(departmentId)) {
      poolKey = 'tech';
    } else if (['dept-ba', 'dept-thm', 'dept-re', 'dept-ie'].includes(departmentId)) {
      poolKey = 'business';
    } else if (['dept-eng', 'dept-jmc', 'dept-law', 'dept-ds'].includes(departmentId)) {
      poolKey = 'humanities';
    } else {
      poolKey = 'science';
    }

    const areas = [
      teachingPool[poolKey][i % teachingPool[poolKey].length],
      teachingPool[poolKey][(i + 1) % teachingPool[poolKey].length],
      teachingPool[poolKey][(i + 2) % teachingPool[poolKey].length]
    ];
    const interests = [
      researchPool[poolKey][i % researchPool[poolKey].length],
      researchPool[poolKey][(i + 1) % researchPool[poolKey].length]
    ];

    // Determine avatar
    const isFemale = ['Farhana', 'Sharmin', 'Sadia', 'Nusrat', 'Tasnim', 'Humaira', 'Laila', 'Fatima'].includes(fName) || lName.includes('Sultana') || lName.includes('Parvin') || lName.includes('Yasmin') || lName.includes('Akter');
    const avatar = isFemale 
      ? `https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&q=80&w=256`
      : `https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&q=80&w=256`;

    extra.push({
      id: `t-${i}`,
      name,
      avatar,
      designation,
      administrativeRole: (i % 30 === 0) ? 'Coordinator' : (i % 45 === 0) ? 'Advisor' : 'None',
      departmentId,
      email: `${emailName}.${departmentId.split('-')[1]}@daffodilvarsity.edu.bd`,
      phone: `+88017${1000000 + i}`,
      office: `Level ${3 + (i % 6)}, Academic Block ${1 + (i % 3)}, DIU Smart City`,
      website: `https://${emailName}.daffodilvarsity.edu.bd`,
      linkedin: `https://linkedin.com/in/${emailName}`,
      googleScholar: `https://scholar.google.com/citations?user=${emailName}`,
      researchGate: `https://researchgate.net/profile/${emailName}`,
      facebook: `https://facebook.com/${emailName}`,
      instagram: `https://instagram.com/${emailName}`,
      github: `https://github.com/${emailName}`,
      bio: `${name} is an active faculty member at Daffodil International University. Dedicated to high-impact classroom instruction and research contributions in ${interests[0]} and ${interests[1]}.`,
      teachingAreas: areas,
      researchInterests: interests,
      publications: [
        {
          id: `pub-${i}01`,
          title: `Empirical Assessment and Deep Analytics of ${interests[0]} in Contemporary Workflows`,
          authors: `${fName.charAt(0)}. ${lName}, K. Ahmed`,
          type: 'Journal',
          venue: `DIU Academic Journal of ${poolKey.toUpperCase()}`,
          year: 2024,
          doi: `10.5555/diu.${i}`,
          abstract: `A deep, empirical review showing systemic patterns and deployment advantages of ${interests[0]}. The methodology details statistical validation and qualitative evaluations.`,
          publisher: 'DIU Press',
          citations: 3 + (i % 12),
          link: '#'
        },
        {
          id: `pub-${i}02`,
          title: `A Framework for Integrating ${interests[1]} in Modern Classrooms`,
          authors: `${fName.charAt(0)}. ${lName}, S. Rahman`,
          type: 'Conference',
          venue: `International Conference on Modern ${poolKey.toUpperCase()}`,
          year: 2023,
          doi: `10.5555/conf.${i}`,
          abstract: `Exploring the pedagogy and active learning strategies utilizing ${interests[1]} architectures to increase student output.`,
          publisher: 'IEEE Computer Society',
          citations: i % 8,
          link: '#'
        }
      ],
      jobExperiences: [
        {
          id: `exp-${i}01`,
          role: designation,
          institution: 'Daffodil International University',
          duration: `${2021 - (i % 4)} - Present`,
          description: `Delivering advanced lectures, supervising student capstones, and contributing to board curriculum designs.`
        },
        {
          id: `exp-${i}02`,
          role: 'Lecturer',
          institution: 'State University of Bangladesh',
          duration: `${2018 - (i % 4)} - ${2021 - (i % 4)}`
        }
      ],
      trainingExperiences: [
        {
          id: `trn-${i}01`,
          title: 'Pedagogy & OBE (Outcome-Based Education) Curriculum',
          organization: 'Institutional Quality Assurance Cell (IQAC), DIU',
          year: 2022,
          duration: '3 Days'
        }
      ],
      awards: [
        {
          id: `aw-${i}01`,
          title: 'Outstanding Teaching Excellence Award',
          organization: 'Daffodil International University',
          year: 2023,
          category: 'Teaching'
        }
      ],
      memberships: [
        `Regular Member, Bangladesh Academic Society of ${poolKey.toUpperCase()}`
      ],
      academicBackground: [
        {
          degree: 'Ph.D. in ' + interests[0],
          institution: 'Dhaka University',
          year: 2018 - (i % 5),
          result: 'First Class'
        },
        {
          degree: 'M.Sc. in ' + areas[0],
          institution: 'Daffodil International University',
          year: 2013 - (i % 5),
          result: 'CGPA 3.92/4.00'
        },
        {
          degree: 'B.Sc. in ' + areas[0],
          institution: 'Daffodil International University',
          year: 2011 - (i % 5),
          result: 'CGPA 3.85/4.00'
        }
      ],
      researchProjects: [
        {
          id: `proj-${i}01`,
          title: `National Initiative for ${interests[0]} development in Rural Hubs`,
          role: 'Principal Investigator',
          fundingBody: 'ICT Division, Government of Bangladesh',
          amount: '500,000 BDT',
          status: 'Ongoing',
          duration: '2023 - 2025'
        }
      ],
      thesisSupervisions: [
        {
          id: `sup-${i}01`,
          title: `Optimizing and Scaling ${areas[0]} for Industrial Production`,
          studentName: 'Md. Al-Amin',
          program: 'BSc',
          year: 2024,
          status: 'Completed'
        }
      ],
      consultancies: [
        {
          id: `cons-${i}01`,
          organization: 'Aarong Dairy / BRAC Enterprises',
          projectTitle: 'Automated workflow deployment advisory',
          year: 2023,
          role: 'Technical Advisor'
        }
      ],
      communityServices: [
        {
          id: `com-${i}01`,
          role: 'Advisor',
          organization: 'DIU Social Service Club',
          duration: '2022 - Present'
        }
      ]
    });
  }
  return extra;
}

export const INITIAL_TEACHERS: Teacher[] = [...BASE_TEACHERS, ...generateExtraTeachers()];
