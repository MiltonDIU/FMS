import React, { useState, useEffect } from 'react';
import { 
  PROVIDED_FACULTIES as INITIAL_FACULTIES, 
  PROVIDED_DEPARTMENTS as INITIAL_DEPARTMENTS 
} from './data/bbaTeachers';
import { PROVIDED_TEACHERS as INITIAL_TEACHERS } from './data/bbaTeachersList';
import { Faculty, Department, Teacher, Publication } from './types';
import { Header } from './components/Header';
import { FacultyCard } from './components/FacultyCard';
import { TeacherCard } from './components/TeacherCard';
import { TeacherProfile } from './components/TeacherProfile';
import { PublicationDetail } from './components/PublicationDetail';
import { LocalApiManager } from './components/LocalApiManager';
import { 
  ChevronRight, ArrowLeft, Filter, Users, LayoutGrid, 
  ShieldCheck, Info, Sparkles, BookOpen, GraduationCap, Lock, Globe
} from 'lucide-react';

export default function App() {
  // Navigation & Directory State
  const [searchQuery, setSearchQuery] = useState('');
  
  // Faculty profiles editable state, loaded as static simulation of the developed API dataset
  const [teachers, setTeachers] = useState<Teacher[]>(INITIAL_TEACHERS);
  const [faculties, setFaculties] = useState<Faculty[]>(INITIAL_FACULTIES);
  const [departments, setDepartments] = useState<Department[]>(INITIAL_DEPARTMENTS);

  // Current navigation states
  const [selectedFacultyId, setSelectedFacultyId] = useState<string | null>(null);
  const [selectedDeptId, setSelectedDeptId] = useState<string | null>(null);
  const [selectedTeacherId, setSelectedTeacherId] = useState<string | null>(null);
  const [selectedPublication, setSelectedPublication] = useState<Publication | null>(null);

  // Search Results & Filters State
  const [designationFilter, setDesignationFilter] = useState<string>('all'); // 'all', 'Professor', 'Associate Professor', etc.
  const [adminOnlyFilter, setAdminOnlyFilter] = useState<string>('all_admin'); // 'all_admin', 'Dean', 'Head', 'none'
  const [pageSize, setPageSize] = useState<number>(12);
  const [currentPage, setCurrentPage] = useState<number>(1);

  // Display Mode & Load More States
  const [displayMode, setDisplayMode] = useState<'pagination' | 'loadmore'>('pagination');
  const [loadedCount, setLoadedCount] = useState<number>(12);

  // Teacher Login States
  const [isLoginModalOpen, setIsLoginModalOpen] = useState<boolean>(false);
  const [loggedInTeacherId, setLoggedInTeacherId] = useState<string | null>(null);
  const [loginEmail, setLoginEmail] = useState<string>('');
  const [loginPassword, setLoginPassword] = useState<string>('');
  const [loginError, setLoginError] = useState<string | null>(null);

  // Reset active page limits and loaded count when filters/selections change
  useEffect(() => {
    setCurrentPage(1);
    setLoadedCount(pageSize);
  }, [designationFilter, adminOnlyFilter, selectedDeptId, selectedFacultyId, searchQuery, pageSize]);

  // Helper variables
  const activeFaculty = faculties.find(f => f.id === selectedFacultyId);
  const activeDept = departments.find(d => d.id === selectedDeptId);
  const activeTeacher = teachers.find(t => t.id === selectedTeacherId);

  // Stats calculation
  const stats = {
    facultiesCount: faculties.length,
    departmentsCount: departments.length,
    teachersCount: teachers.length
  };

  // --- FILTER & SEARCH LOGIC ---
  const getFilteredTeachers = () => {
    let list = [...teachers];

    // 1. Filter by General search query (Header)
    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase();
      list = list.filter(t => {
        const nameMatch = (t.name || '').toLowerCase().includes(q);
        
        const areas = t.teachingAreas || [];
        const areasMatch = areas.some(area => {
          const areaName = typeof area === 'string' ? area : area.area;
          return (areaName || '').toLowerCase().includes(q);
        });

        const interests = t.researchInterests || 
          (t.research_interest ? t.research_interest.split(',') : []);
        const interestsMatch = interests.some(interest => 
          (interest || '').toLowerCase().includes(q)
        );

        const pubs = t.publications || [];
        const pubsMatch = pubs.some(pub => 
          (pub.title || '').toLowerCase().includes(q)
        );

        return nameMatch || areasMatch || interestsMatch || pubsMatch;
      });
    }

    // 2. Filter by Faculty scope if selected
    if (selectedFacultyId && !selectedDeptId) {
      const deptsInFaculty = departments.filter(d => d.facultyId === selectedFacultyId).map(d => d.id);
      list = list.filter(t => deptsInFaculty.includes(t.departmentId));
    }

    // 3. Filter by Department scope if selected
    if (selectedDeptId) {
      list = list.filter(t => t.departmentId === selectedDeptId);
    }

    // 4. Left Sidebar Designation Filter
    if (designationFilter !== 'all') {
      list = list.filter(t => t.designation === designationFilter);
    }

    // 5. Left Sidebar Administrative Role Filter
    if (adminOnlyFilter === 'administrative_only') {
      list = list.filter(t => t.administrativeRole && t.administrativeRole !== 'None');
    } else if (adminOnlyFilter !== 'all_admin' && adminOnlyFilter !== 'all') {
      list = list.filter(t => t.administrativeRole === adminOnlyFilter);
    }

    // 6. Sort Strategy: Administrative members ALWAYS on top by default, then regular sorted list
    return list.sort((a, b) => {
      const aIsAdmin = a.administrativeRole && a.administrativeRole !== 'None' ? 1 : 0;
      const bIsAdmin = b.administrativeRole && b.administrativeRole !== 'None' ? 1 : 0;
      if (aIsAdmin !== bIsAdmin) {
        return bIsAdmin - aIsAdmin; // Administrative on top
      }
      return a.name.localeCompare(b.name); // Then alphabetical order
    });
  };

  const filteredTeachers = getFilteredTeachers();

  // Pagination bounds
  const paginatedTeachers = filteredTeachers.slice(
    (currentPage - 1) * pageSize,
    currentPage * pageSize
  );
  
  // Handled Display List (Pagination vs Load More)
  const displayedTeachers = displayMode === 'pagination'
    ? paginatedTeachers
    : filteredTeachers.slice(0, loadedCount);

  const totalPages = Math.ceil(filteredTeachers.length / pageSize);

  // Quick navigation handlers
  const handleFacultyClick = (facId: string) => {
    setSelectedFacultyId(facId);
    setSelectedDeptId(null);
    setSelectedTeacherId(null);
    setSelectedPublication(null);
  };

  const handleDeptClick = (deptId: string) => {
    const dept = departments.find(d => d.id === deptId);
    if (dept) {
      setSelectedFacultyId(dept.facultyId);
    }
    setSelectedDeptId(deptId);
    setSelectedTeacherId(null);
    setSelectedPublication(null);
  };

  const handleBackToFaculties = () => {
    setSelectedFacultyId(null);
    setSelectedDeptId(null);
    setSelectedTeacherId(null);
    setSelectedPublication(null);
  };

  const handleBackToDepartments = () => {
    setSelectedDeptId(null);
    setSelectedTeacherId(null);
    setSelectedPublication(null);
  };

  // Profile update handler (saves in active session state)
  const handleUpdateTeacher = (updatedTeacher: Teacher) => {
    setTeachers(prev => prev.map(t => t.id === updatedTeacher.id ? updatedTeacher : t));
  };

  // Simulated Login Handler
  const handleSimulatedLogin = (teacherId: string) => {
    setLoggedInTeacherId(teacherId);
    setIsLoginModalOpen(false);
    
    // Auto navigation to logged in profile for high fidelity edit testing
    setSelectedTeacherId(teacherId);
    setSelectedPublication(null);
    
    const teacherObj = teachers.find(t => t.id === teacherId);
    if (teacherObj) {
      setSelectedDeptId(teacherObj.departmentId);
      const deptObj = departments.find(d => d.id === teacherObj.departmentId);
      if (deptObj) {
        setSelectedFacultyId(deptObj.facultyId);
      }
    }
  };

  // Secure / Real Login submit handler checking emails
  const handleLoginSubmit = (emailStr: string, passwordStr: string) => {
    setLoginError(null);
    const trimmedEmail = emailStr.trim().toLowerCase();
    
    if (!trimmedEmail) {
      setLoginError("Please enter your Scholar ID (Email).");
      return false;
    }
    if (!passwordStr.trim()) {
      setLoginError("Please enter your password.");
      return false;
    }
    
    const foundTeacher = teachers.find(t => t.email.toLowerCase() === trimmedEmail);
    if (foundTeacher) {
      handleSimulatedLogin(foundTeacher.id);
      setLoginEmail('');
      setLoginPassword('');
      setIsLoginModalOpen(false); // Close modal on success!
      return true;
    } else {
      setLoginError("No scholar found with this email. Please check the email spelling or use the shortcut buttons.");
      return false;
    }
  };

  return (
    <div className="min-h-screen bg-transparent flex flex-col font-sans text-slate-800" id="diu-app-container">
      {/* HEADER SECTION */}
      <Header 
        onSearch={setSearchQuery} 
        searchQuery={searchQuery}
        onLoginClick={() => setIsLoginModalOpen(true)}
        stats={stats}
      />

      {/* ACTIVE LOGGED-IN SESSION NOTIFICATION STRIP */}
      {loggedInTeacherId && (
        <div className="bg-gradient-to-r from-diu-green-light to-diu-blue-light text-white py-2 px-4 text-xs font-semibold flex items-center justify-between shadow-xs z-30 font-sans">
          <div className="flex items-center gap-2 max-w-lg truncate">
            <span className="bg-white/25 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider">Session Active</span>
            <span>Logged in as <strong>{teachers.find(t => t.id === loggedInTeacherId)?.name || 'Teacher'}</strong></span>
          </div>
          <div className="flex items-center gap-3">
            <button 
              onClick={() => {
                setSelectedTeacherId(loggedInTeacherId);
                setSelectedPublication(null);
              }}
              className="underline hover:text-blue-100 cursor-pointer font-bold shrink-0"
            >
              Go to My Profile
            </button>
            <span className="opacity-40">|</span>
            <button 
              onClick={() => setLoggedInTeacherId(null)}
              className="bg-white text-slate-800 px-3 py-1 rounded-md text-[10px] font-black uppercase hover:bg-slate-100 transition-colors shrink-0 cursor-pointer"
            >
              Log Out
            </button>
          </div>
        </div>
      )}

      {/* BODY CONTENT WRAPPER */}
      <main className="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-8">
        
        {/* PORTAL VIEWS */}
        <div className="space-y-6">
            
            {/* Breadcrumb Navigation Strip */}
            <div className="flex flex-wrap items-center gap-2 text-xs text-slate-500 font-sans py-2.5 px-5 bg-white/40 backdrop-blur-md rounded-2xl border border-white/60 shadow-sm">
              <button 
                onClick={handleBackToFaculties}
                className="hover:text-diu-green font-semibold transition-colors"
              >
                DIU Faculties
              </button>

              {activeFaculty && (
                <>
                  <ChevronRight className="w-3.5 h-3.5 text-gray-300" />
                  <button 
                    onClick={() => { setSelectedDeptId(null); setSelectedTeacherId(null); setSelectedPublication(null); }}
                    className="hover:text-diu-green font-semibold transition-colors truncate max-w-xs"
                  >
                    {activeFaculty.code}
                  </button>
                </>
              )}

              {activeDept && (
                <>
                  <ChevronRight className="w-3.5 h-3.5 text-gray-300" />
                  <button 
                    onClick={() => { setSelectedTeacherId(null); setSelectedPublication(null); }}
                    className="hover:text-diu-green font-semibold transition-colors truncate max-w-xs"
                  >
                    {activeDept.code}
                  </button>
                </>
              )}

              {activeTeacher && (
                <>
                  <ChevronRight className="w-3.5 h-3.5 text-gray-300" />
                  <span className="text-gray-400 font-medium truncate max-w-xs">{activeTeacher.name}</span>
                </>
              )}

              {selectedPublication && (
                <>
                  <ChevronRight className="w-3.5 h-3.5 text-gray-300" />
                  <span className="text-gray-400 font-medium truncate max-w-xs font-mono">Publication</span>
                </>
              )}
            </div>

            {/* SUB-VIEW A: VIEWING PUBLICATION DETAIL (HIGHEST PRIORITY SUB-PAGE) */}
            {selectedPublication ? (
              <PublicationDetail 
                publication={selectedPublication}
                authorTeacher={activeTeacher}
                onBack={() => setSelectedPublication(null)}
              />
            ) : activeTeacher ? (
              /* SUB-VIEW B: TEACHER PROFILE */
              <TeacherProfile 
                teacher={activeTeacher}
                departmentName={activeDept?.name || departments.find(d => d.id === activeTeacher.departmentId)?.name || 'DIU Department'}
                facultyName={activeFaculty?.name || 'DIU'}
                onBack={() => setSelectedTeacherId(null)}
                onSelectPublication={(pub) => setSelectedPublication(pub)}
                isOwnProfile={loggedInTeacherId === activeTeacher.id}
                onUpdateTeacher={handleUpdateTeacher}
              />
            ) : (
              /* SUB-VIEW C: DYNAMIC EXPLORER FRAME (Faculties / Departments / Teachers list) */
              <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
                
                {/* LEFT SIDEBAR: Conditionally changes based on navigation scope */}
                <div className="lg:col-span-1 space-y-5">
                  
                  {/* Left Sidebar Menu 1: Faculty Navigation menu */}
                  <div className="bg-white/50 backdrop-blur-md rounded-2xl border border-white/60 p-5 shadow-sm ring-1 ring-diu-green/5">
                    <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-1.5">
                      <LayoutGrid className="w-4 h-4 text-diu-green" />
                      Academic Faculties
                    </h3>
                    <div className="space-y-1.5">
                      {faculties.map(fac => (
                        <button
                          key={fac.id}
                          onClick={() => handleFacultyClick(fac.id)}
                          className={`w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold font-sans tracking-tight transition-all flex items-center justify-between ${
                            selectedFacultyId === fac.id && !selectedDeptId
                              ? 'bg-diu-green/15 text-diu-green shadow-xs'
                              : 'hover:bg-white/40 text-slate-600 hover:text-slate-900'
                          }`}
                        >
                          <span className="truncate">{fac.name}</span>
                          <span className="bg-white/60 text-slate-500 text-[9px] font-bold px-1.5 py-0.5 rounded-sm shrink-0 border border-white/60">
                            {fac.code}
                          </span>
                        </button>
                      ))}
                    </div>
                  </div>

                  {/* Left Sidebar Menu 2: Dynamic Department list under selected Faculty */}
                  {selectedFacultyId && (
                    <div className="bg-white/50 backdrop-blur-md rounded-2xl border border-white/60 p-5 shadow-sm ring-1 ring-diu-orange/5">
                      <h3 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-1.5">
                        <GraduationCap className="w-4 h-4 text-diu-orange" />
                        Departments
                      </h3>
                      <div className="space-y-1">
                        {departments
                          .filter(d => d.facultyId === selectedFacultyId)
                          .map(dept => (
                            <button
                              key={dept.id}
                              onClick={() => handleDeptClick(dept.id)}
                              className={`w-full text-left px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all flex items-center justify-between ${
                                selectedDeptId === dept.id
                                  ? 'bg-diu-orange/15 text-diu-orange font-bold shadow-xs'
                                  : 'hover:bg-white/40 text-slate-600 hover:text-slate-900'
                              }`}
                            >
                              <span className="truncate">{dept.name}</span>
                              <ChevronRight className="w-3.5 h-3.5 shrink-0 ml-1 opacity-60" />
                            </button>
                          ))}
                      </div>
                    </div>
                  )}

                  {/* Left Sidebar Menu 3: Dynamic Designation / Administrative filters when displaying teachers */}
                  {(selectedFacultyId || selectedDeptId || searchQuery) && (
                    <div className="bg-white/50 backdrop-blur-md rounded-2xl border border-white/60 p-5 shadow-sm ring-1 ring-slate-900/5 space-y-6">
                      
                      {/* Filter group A: Administrative Role Filter (Shows above Designation as requested) */}
                      <div>
                        <h4 className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                          <ShieldCheck className="w-4 h-4 text-diu-orange" />
                          Administrative Roles
                        </h4>
                        <div className="space-y-1">
                          {[
                            { id: 'all_admin', label: 'All Administrative Roles' },
                            { id: 'administrative_only', label: 'Any Active Admin Role' },
                            { id: 'Dean', label: 'Deans of Faculties' },
                            { id: 'Head of Department', label: 'Heads of Departments' },
                            { id: 'Coordinator', label: 'Batch Coordinators' },
                            { id: 'Advisor', label: 'Student Advisors' }
                          ].map(opt => (
                            <button
                              key={opt.id}
                              onClick={() => {
                                setAdminOnlyFilter(opt.id);
                                // If choosing specific admin roles, let designation default to all to avoid empty states
                                if (opt.id !== 'all_admin') setDesignationFilter('all');
                              }}
                              className={`w-full text-left px-3 py-2 rounded-lg text-xs font-medium font-sans transition-all flex items-center gap-2 ${
                                adminOnlyFilter === opt.id
                                  ? 'bg-diu-orange/10 text-diu-orange font-bold border-l-2 border-diu-orange pl-2.5'
                                  : 'text-slate-500 hover:text-slate-800 hover:bg-white/30'
                              }`}
                            >
                              <div className={`w-1.5 h-1.5 rounded-full ${adminOnlyFilter === opt.id ? 'bg-diu-orange' : 'bg-slate-300'}`} />
                              {opt.label}
                            </button>
                          ))}
                        </div>
                      </div>

                      {/* Filter group B: Regular Academic Designation Filter */}
                      <div>
                        <h4 className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                          <Filter className="w-4 h-4 text-diu-green" />
                          Academic Designations
                        </h4>
                        <div className="space-y-1">
                          {[
                            { id: 'all', label: 'All Designations' },
                            { id: 'Professor', label: 'Professors' },
                            { id: 'Associate Professor', label: 'Associate Professors' },
                            { id: 'Assistant Professor', label: 'Assistant Professors' },
                            { id: 'Senior Lecturer', label: 'Senior Lecturers' },
                            { id: 'Lecturer', label: 'Lecturers' }
                          ].map(opt => (
                            <button
                              key={opt.id}
                              onClick={() => {
                                setDesignationFilter(opt.id);
                                // If specific designation selected, reset admin filter
                                if (opt.id !== 'all') setAdminOnlyFilter('all_admin');
                              }}
                              className={`w-full text-left px-3 py-2 rounded-lg text-xs font-medium font-sans transition-all flex items-center gap-2 ${
                                designationFilter === opt.id
                                  ? 'bg-diu-green/10 text-diu-green font-bold border-l-2 border-diu-green pl-2.5'
                                  : 'text-slate-500 hover:text-slate-800 hover:bg-white/30'
                              }`}
                            >
                              <div className={`w-1.5 h-1.5 rounded-full ${designationFilter === opt.id ? 'bg-diu-green' : 'bg-slate-300'}`} />
                              {opt.label}
                            </button>
                          ))}
                        </div>
                      </div>

                      {/* Quick statistical details of filtering */}
                      <div className="pt-3 border-t border-white/60">
                        <div className="flex justify-between text-[11px] text-slate-400 font-sans font-medium">
                          <span>Matching Scholars:</span>
                          <span className="font-bold text-slate-700">{filteredTeachers.length}</span>
                        </div>
                      </div>

                    </div>
                  )}

                </div>

                {/* RIGHT CONTENT STAGE */}
                <div className="lg:col-span-3 space-y-6">

                  {/* MAIN INTRO BANNER using brand Green and Deep Blue combination */}
                  {!selectedDeptId && !searchQuery && (
                    <div className="bg-gradient-to-br from-diu-green-dark via-diu-green to-diu-blue border border-white/20 backdrop-blur-md p-6 md:p-8 rounded-2xl text-white shadow-lg relative overflow-hidden">
                      <div className="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -mr-20 -mt-20 blur-3xl pointer-events-none" />
                      <div className="absolute bottom-0 left-0 w-32 h-32 bg-diu-orange/20 rounded-full -ml-12 -mb-12 blur-2xl pointer-events-none" />
                      
                      <div className="relative z-10 max-w-xl">
                        <div className="flex items-center gap-2 mb-2">
                          <Sparkles className="w-4 h-4 text-diu-orange" />
                          <span className="text-[10px] uppercase font-bold tracking-widest text-diu-orange">Smart Academic Portal</span>
                        </div>
                        <h2 className="text-xl md:text-2xl font-display font-extrabold leading-tight tracking-tight">
                          Daffodil International University Faculty Directory
                        </h2>
                        <p className="text-xs text-white/85 font-sans mt-2.5 leading-relaxed">
                          Welcome to the official, modernized Scholar profile portal. Explore academic credentials, award catalogs, research interest matrices, and generate citations for publication details.
                        </p>
                      </div>
                    </div>
                  )}

                  {/* CASE 1: HOME PAGE (Viewing all Faculties) */}
                  {!selectedFacultyId && !searchQuery && (
                    <div className="space-y-4">
                      <div className="flex items-center gap-2">
                        <div className="h-4 w-1 bg-diu-green rounded-xs" />
                        <h3 className="font-display font-bold text-md text-gray-800">Explore by Academic Faculties</h3>
                      </div>
                      
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {faculties.map(fac => (
                          <FacultyCard 
                            key={fac.id}
                            faculty={fac}
                            departments={departments}
                            teachers={teachers}
                            onClick={() => handleFacultyClick(fac.id)}
                          />
                        ))}
                      </div>
                    </div>
                  )}

                  {/* CASE 2: SEARCH ACTIVE OR SCOPED TO FACULTY/DEPARTMENT (Viewing Teachers grid) */}
                  {(selectedFacultyId || searchQuery) && (
                    <div className="space-y-6">
                      
                      {/* Active filter category title */}
                      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-gray-100 gap-3">
                        <div>
                          <div className="flex items-center gap-2 text-xs font-semibold text-gray-400 font-sans">
                            <span>{activeFaculty ? activeFaculty.name : 'DIU Directory'}</span>
                            {activeDept && (
                              <>
                                <ChevronRight className="w-3 h-3" />
                                <span className="text-diu-green">{activeDept.name}</span>
                              </>
                            )}
                          </div>
                          
                          <h3 className="font-display font-extrabold text-lg text-gray-800 mt-1 flex items-center gap-2">
                            <Users className="w-5 h-5 text-diu-green" />
                            {activeDept ? activeDept.name : activeFaculty ? `${activeFaculty.code} Scholars` : 'Search Directory'}
                          </h3>
                        </div>

                        {/* Pagination vs Load more switch controls (Handles high capacity load testing) */}
                        <div className="flex flex-wrap items-center gap-4 text-xs font-sans">
                          {/* Layout Toggler */}
                          <div className="flex items-center gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200/50">
                            <button
                              onClick={() => setDisplayMode('pagination')}
                              className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                                displayMode === 'pagination'
                                  ? 'bg-white text-diu-green shadow-xs'
                                  : 'text-slate-500 hover:text-slate-850'
                              }`}
                              title="Page-by-page rendering (standard structured navigation)"
                            >
                              Pagination
                            </button>
                            <button
                              onClick={() => setDisplayMode('loadmore')}
                              className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                                displayMode === 'loadmore'
                                  ? 'bg-white text-diu-green shadow-xs'
                                  : 'text-slate-500 hover:text-slate-850'
                              }`}
                              title="Continuous listing with 'Load More' (best for departments with 200-400 scholars)"
                            >
                              Load More
                            </button>
                          </div>

                          {displayMode === 'pagination' && (
                            <div className="flex items-center gap-2 text-slate-500">
                              <span>Page Size:</span>
                              <select 
                                value={pageSize}
                                onChange={(e) => {
                                  setPageSize(Number(e.target.value));
                                  setCurrentPage(1);
                                }}
                                className="bg-white border border-slate-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-diu-green font-semibold"
                              >
                                <option value={10}>10 Scholars</option>
                                <option value={12}>12 Scholars</option>
                                <option value={15}>15 Scholars</option>
                              </select>
                            </div>
                          )}
                        </div>
                      </div>

                      {/* Displaying teachers list */}
                      {displayedTeachers.length > 0 ? (
                        <div className="space-y-6">
                          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                            {displayedTeachers.map(teacher => {
                              const dept = departments.find(d => d.id === teacher.departmentId);
                              return (
                                <TeacherCard 
                                  key={teacher.id}
                                  teacher={teacher}
                                  departmentName={dept?.name || 'Department'}
                                  onClick={() => setSelectedTeacherId(teacher.id)}
                                />
                              );
                            })}
                          </div>

                          {/* OPTION A: Pagination Block */}
                          {displayMode === 'pagination' && totalPages > 1 && (
                            <div className="flex items-center justify-center gap-1.5 pt-6 border-t border-gray-100 font-sans">
                              <button
                                onClick={() => setCurrentPage(prev => Math.max(prev - 1, 1))}
                                disabled={currentPage === 1}
                                className="px-3.5 py-1.5 border border-gray-200 rounded-lg text-xs font-semibold disabled:opacity-40 hover:bg-gray-50 transition-all text-gray-600 cursor-pointer"
                              >
                                Previous
                              </button>
                              
                              {Array.from({ length: totalPages }).map((_, idx) => (
                                <button
                                  key={idx}
                                  onClick={() => setCurrentPage(idx + 1)}
                                  className={`w-8 h-8 rounded-lg text-xs font-semibold transition-all cursor-pointer ${
                                    currentPage === idx + 1
                                      ? 'bg-diu-green text-white font-bold shadow-md shadow-diu-green/10'
                                      : 'border border-gray-200 text-gray-600 hover:bg-gray-50'
                                  }`}
                                >
                                  {idx + 1}
                                </button>
                              ))}

                              <button
                                onClick={() => setCurrentPage(prev => Math.min(prev + 1, totalPages))}
                                disabled={currentPage === totalPages}
                                className="px-3.5 py-1.5 border border-gray-200 rounded-lg text-xs font-semibold disabled:opacity-40 hover:bg-gray-50 transition-all text-gray-600 cursor-pointer"
                              >
                                Next
                              </button>
                            </div>
                          )}

                          {/* OPTION B: Load More Block (Recommended for 200–400 profiles) */}
                          {displayMode === 'loadmore' && (
                            <div className="pt-6 border-t border-slate-100 flex flex-col items-center gap-3">
                              <p className="text-xs text-slate-400 font-medium font-sans">
                                Showing <strong>{displayedTeachers.length}</strong> of <strong>{filteredTeachers.length}</strong> Scholars
                              </p>
                              {loadedCount < filteredTeachers.length ? (
                                <button
                                  onClick={() => setLoadedCount(prev => Math.min(prev + 12, filteredTeachers.length))}
                                  className="px-6 py-2.5 bg-white border border-slate-200 text-diu-green font-bold text-xs rounded-xl hover:bg-slate-50 hover:border-diu-green/30 transition-all shadow-3xs cursor-pointer active:scale-95"
                                >
                                  Load More Scholars
                                </button>
                              ) : (
                                <span className="bg-slate-100 text-slate-500 font-bold text-[10px] uppercase px-4 py-1.5 rounded-full tracking-wider border border-slate-200">
                                  All Scholars Loaded
                                </span>
                              )}
                            </div>
                          )}

                        </div>
                      ) : (
                        <div className="text-center py-16 bg-white/40 backdrop-blur-md border border-white/60 rounded-2xl shadow-sm">
                          <Users className="w-12 h-12 text-slate-400 mx-auto mb-3" />
                          <h4 className="text-sm font-bold text-slate-800 font-display">No scholars found</h4>
                          <p className="text-xs text-slate-500 font-sans max-w-sm mx-auto mt-1 leading-relaxed">
                            No faculty members match your selected designations or filter combinations. Try clearing some side-filters.
                          </p>
                          <button
                            onClick={() => {
                              setDesignationFilter('all');
                              setAdminOnlyFilter('all_admin');
                              setSearchQuery('');
                            }}
                            className="mt-4 px-4 py-2 bg-diu-green hover:bg-diu-green-hover text-white text-xs font-bold rounded-lg transition-colors cursor-pointer"
                          >
                            Reset Active Filters
                          </button>
                        </div>
                      )}

                    </div>
                  )}

                </div>

              </div>
            )}

        </div>

      </main>

      {/* FOOTER SECTION */}
      <footer className="bg-slate-950 border-t border-slate-800 text-slate-400 text-xs py-8 mt-12 font-sans" id="diu-footer">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex flex-col md:flex-row justify-between items-center gap-4 border-b border-slate-800 pb-6 mb-6">
            <div className="text-center md:text-left">
              <div className="flex items-center justify-center md:justify-start gap-2">
                <span className="font-display font-black text-sm text-white tracking-wide">DAFFODIL INTERNATIONAL UNIVERSITY</span>
              </div>
              <p className="text-[10px] text-slate-500 mt-1 uppercase tracking-widest font-semibold">Official Scholar Profile & Citation Directory</p>
            </div>
            
            <div className="flex gap-4 text-[11px] font-semibold text-slate-300">
              <span className="text-diu-orange">Smart City Campus, Dhaka</span>
            </div>
          </div>

          <div className="flex flex-col sm:flex-row justify-between items-center gap-2 text-[10px] text-slate-500">
            <p>© 2026 Daffodil International University. All rights reserved.</p>
            <p>BAETE & IEB Accredited Smart Campus, Savar, Dhaka, Bangladesh.</p>
          </div>
        </div>
      </footer>

      {/* TEACHER SSO ACCESS MODAL */}
      {isLoginModalOpen && (
        <div className="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
          <div className="flex items-center justify-center min-h-screen p-4 text-center sm:block sm:p-0">
            {/* Background Overlay */}
            <div 
              className="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" 
              onClick={() => setIsLoginModalOpen(false)}
            />

            {/* Centered Modal Content Card */}
            <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div className="inline-block align-middle bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md w-full border border-slate-200 relative z-10">
              
              {/* Modal Header */}
              <div className="bg-gradient-to-r from-diu-green-dark via-diu-green to-diu-blue p-6 text-white relative">
                <button 
                  onClick={() => setIsLoginModalOpen(false)}
                  className="absolute top-4 right-4 text-white/70 hover:text-white hover:scale-110 transition-all font-sans text-sm font-bold cursor-pointer"
                >
                  ✕
                </button>
                <div className="flex items-center gap-2 mb-1">
                  <Lock className="w-3.5 h-3.5 text-diu-orange" />
                  <span className="text-[9px] uppercase font-bold tracking-widest text-diu-orange">DIU Smart Gateway</span>
                </div>
                <h3 className="text-lg font-display font-extrabold leading-tight tracking-tight">Faculty Access Portal</h3>
                <p className="text-xs text-white/80 mt-1">Simulated single sign-on for Daffodil International University Scholars.</p>
              </div>

              {/* Modal Body */}
              <div className="p-6 space-y-5 bg-slate-50/50">
                
                {/* Simulated Notification Info */}
                <div className="bg-emerald-50 border border-emerald-100 rounded-xl p-3.5 text-[11px] text-emerald-800 font-sans leading-relaxed flex gap-2.5 items-start">
                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0 mt-1.5" />
                  <div>
                    <strong>Daffodil Secure Access</strong>: Log in with your corporate email to update research indexes, edit course lists, or add publication records.
                  </div>
                </div>

                {/* Simulated Username / Password fields */}
                {loginError && (
                  <div className="p-3 bg-red-50 border border-red-100 rounded-xl text-xs text-red-700 font-medium leading-relaxed font-sans">
                    {loginError}
                  </div>
                )}

                {/* SSO Button & Credentials form */}
                <div className="space-y-4">
                  {/* DIU SSO Button */}
                  <button 
                    onClick={() => {
                      if (teachers.length > 0) {
                        handleSimulatedLogin(teachers[0].id);
                        setIsLoginModalOpen(false);
                      }
                    }}
                    className="w-full bg-gradient-to-r from-diu-green to-emerald-600 hover:from-diu-green-hover hover:to-emerald-700 text-white text-xs font-bold py-3 rounded-xl transition-all shadow-md cursor-pointer active:scale-98 flex items-center justify-center gap-2 border border-emerald-500/30"
                  >
                    <Globe className="w-4 h-4 text-diu-orange shrink-0 animate-pulse" />
                    <span>Login with DIU SSO</span>
                  </button>

                  <div className="relative flex py-1 items-center">
                    <div className="flex-grow border-t border-slate-200"></div>
                    <span className="flex-shrink mx-3 text-[9px] text-slate-400 font-extrabold uppercase tracking-widest font-sans">Or use Scholar Credentials</span>
                    <div className="flex-grow border-t border-slate-200"></div>
                  </div>

                  <div className="space-y-3">
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5 font-sans">DIU Scholar ID (Email)</label>
                      <input 
                        type="email" 
                        placeholder="e.g. syed.cse@daffodilvarsity.edu.bd" 
                        value={loginEmail}
                        onChange={(e) => {
                          setLoginEmail(e.target.value);
                          setLoginError(null);
                        }}
                        className="w-full text-xs px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white focus:outline-none focus:ring-1 focus:ring-diu-green focus:border-diu-green"
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5 font-sans">Security Key (Password)</label>
                      <input 
                        type="password" 
                        placeholder="••••••••" 
                        value={loginPassword}
                        onChange={(e) => {
                          setLoginPassword(e.target.value);
                          setLoginError(null);
                        }}
                        className="w-full text-xs px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white focus:outline-none focus:ring-1 focus:ring-diu-green focus:border-diu-green"
                      />
                    </div>
                    
                    <button 
                      onClick={() => handleLoginSubmit(loginEmail, loginPassword)}
                      className="w-full bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold py-2.5 rounded-xl transition-all shadow-sm cursor-pointer active:scale-98"
                    >
                      Secure Sign In
                    </button>
                  </div>
                </div>

                <div className="relative flex py-1 items-center">
                  <div className="flex-grow border-t border-slate-200"></div>
                  <span className="flex-shrink mx-3 text-[9px] text-slate-400 font-bold uppercase tracking-widest font-sans">OR QUICK LOG IN AS</span>
                  <div className="flex-grow border-t border-slate-200"></div>
                </div>

                {/* Tap-to-login options */}
                <div className="space-y-1.5 max-h-44 overflow-y-auto pr-1">
                  {teachers.slice(0, 4).map(t => (
                    <button
                      key={t.id}
                      onClick={() => {
                        handleSimulatedLogin(t.id);
                        setIsLoginModalOpen(false);
                      }}
                      className="w-full text-left p-2.5 bg-white hover:bg-slate-50 border border-slate-200/60 rounded-xl transition-all flex items-center gap-3 group cursor-pointer"
                    >
                      <div className="w-8 h-8 rounded-lg bg-diu-green/10 text-diu-green font-bold text-xs flex items-center justify-center shrink-0">
                        {t.name.split(' ').slice(-1)[0][0]}
                      </div>
                      <div className="min-w-0">
                        <p className="text-xs font-bold text-slate-800 truncate group-hover:text-diu-green transition-colors">{t.name}</p>
                        <p className="text-[10px] text-slate-400 truncate">{t.designation}</p>
                      </div>
                    </button>
                  ))}
                </div>

              </div>

              {/* Modal Footer */}
              <div className="bg-slate-50 px-6 py-4 border-t border-slate-100 flex justify-end">
                <button 
                  onClick={() => setIsLoginModalOpen(false)}
                  className="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-semibold hover:bg-slate-100 transition-colors cursor-pointer"
                >
                  Cancel
                </button>
              </div>

            </div>
          </div>
        </div>
      )}

      {/* LOCAL API BRIDGE CONSOLE */}
      <LocalApiManager 
        currentTeachersCount={teachers.length}
        onDataLoaded={(data) => {
          setTeachers(data.teachers);
          setDepartments(data.departments);
          setFaculties(data.faculties);
        }}
        onReset={() => {
          setTeachers(INITIAL_TEACHERS);
          setDepartments(INITIAL_DEPARTMENTS);
          setFaculties(INITIAL_FACULTIES);
        }}
      />

    </div>
  );
}
