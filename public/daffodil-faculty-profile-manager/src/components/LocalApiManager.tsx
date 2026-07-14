import React, { useState, useEffect } from 'react';
import { Database, Wifi, WifiOff, Settings, CheckCircle2, AlertCircle, RefreshCw, Sliders, Info, X } from 'lucide-react';
import { Teacher, Department, Faculty } from '../types';

interface LocalApiManagerProps {
  onDataLoaded: (data: { teachers: Teacher[]; departments: Department[]; faculties: Faculty[] }) => void;
  onReset: () => void;
  currentTeachersCount: number;
}

export const LocalApiManager: React.FC<LocalApiManagerProps> = ({
  onDataLoaded,
  onReset,
  currentTeachersCount
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [baseUrl, setBaseUrl] = useState(() => localStorage.getItem('diu_api_base_url') || 'http://localhost:8000');
  const [status, setStatus] = useState<'idle' | 'loading' | 'connected' | 'error'>(() => {
    return localStorage.getItem('diu_api_connected') === 'true' ? 'connected' : 'idle';
  });
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [stats, setStats] = useState<{ teachers: number; departments: number; faculties: number } | null>(null);

  // Auto-connect on mount if previously configured
  useEffect(() => {
    if (status === 'connected') {
      handleConnect(true);
    }
  }, []);

  const handleConnect = async (isAuto = false) => {
    setStatus('loading');
    setErrorMsg(null);

    const cleanBaseUrl = baseUrl.trim().replace(/\/$/, '');

    // List of endpoint patterns to try
    const facultyEndpoints = [
      `${cleanBaseUrl}/api/v1/faculties`,
      `${cleanBaseUrl}/api/faculties`,
      `${cleanBaseUrl}/faculties`,
      `${cleanBaseUrl}/api/faculty`,
      `${cleanBaseUrl}/faculty`
    ];
    const deptEndpoints = [
      `${cleanBaseUrl}/api/v1/departments`,
      `${cleanBaseUrl}/api/departments`,
      `${cleanBaseUrl}/departments`,
      `${cleanBaseUrl}/api/department`,
      `${cleanBaseUrl}/department`
    ];
    const teacherEndpoints = [
      `${cleanBaseUrl}/api/v1/departments/bba/teachers`,
      `${cleanBaseUrl}/api/v1/teachers`,
      `${cleanBaseUrl}/api/teachers`,
      `${cleanBaseUrl}/teachers`,
      `${cleanBaseUrl}/api/teacher`,
      `${cleanBaseUrl}/teacher`
    ];

    const findArrayInResponse = (obj: any): any[] | null => {
      if (!obj) return null;
      if (Array.isArray(obj)) return obj;
      if (Array.isArray(obj.data)) return obj.data;
      if (Array.isArray(obj.results)) return obj.results;
      if (Array.isArray(obj.teachers)) return obj.teachers;
      if (Array.isArray(obj.departments)) return obj.departments;
      if (Array.isArray(obj.faculties)) return obj.faculties;
      
      for (const key of Object.keys(obj)) {
        if (Array.isArray(obj[key])) {
          return obj[key];
        }
        if (obj[key] && typeof obj[key] === 'object') {
          const nested = findArrayInResponse(obj[key]);
          if (nested) return nested;
        }
      }
      return null;
    };

    const fetchFirstSuccessful = async (urls: string[]) => {
      let lastError = '';
      for (const url of urls) {
        try {
          const res = await fetch(url, { method: 'GET', headers: { 'Accept': 'application/json' } });
          if (res.ok) {
            const data = await res.json();
            const arr = findArrayInResponse(data);
            if (arr) {
              return { url, data: arr };
            }
          }
        } catch (e: any) {
          lastError = e.message || String(e);
        }
      }
      throw new Error(lastError || 'Could not fetch valid JSON array from endpoints');
    };

    try {
      // 1. Fetch faculties
      const facResult = await fetchFirstSuccessful(facultyEndpoints);
      // 2. Fetch departments
      const deptResult = await fetchFirstSuccessful(deptEndpoints);

      // Clean up and map the fetched structures dynamically to meet application TS interfaces
      const mappedFaculties: Faculty[] = facResult.data.map((f: any, idx: number) => ({
        id: String(f.id || f.faculty_id || f.code || `fac-${idx}`),
        name: f.name || f.faculty_name || 'Faculty',
        code: f.code || f.short_name || 'FAC',
        deanId: f.deanId || f.dean_id || '',
        description: f.description || '',
        image: f.image || f.banner_image || 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?auto=format&fit=crop&q=80&w=400'
      }));

      const mappedDepartments: Department[] = deptResult.data.map((d: any, idx: number) => ({
        id: String(d.id || d.department_id || d.code || `dept-${idx}`),
        name: d.name || d.department_name || 'Department',
        code: d.code || d.short_name || 'DEPT',
        facultyId: String(d.faculty_id || d.facultyId || d.facultyId || 'fac-2'),
        description: d.description || ''
      }));

      // 3. Intelligently fetch teachers collection: either try specific department-wise endpoints or general fallback list
      let rawTeachers: any[] = [];
      const deptSlugs = new Set<string>(['bba', 'ba']); // default fallback
      mappedDepartments.forEach(d => {
        if (d.code) deptSlugs.add(d.code.toLowerCase());
        if (d.short_name) deptSlugs.add(d.short_name.toLowerCase());
        if (d.id && typeof d.id === 'string') deptSlugs.add(d.id.toLowerCase().replace('dept-', ''));
      });

      const deptTeachers: any[] = [];
      await Promise.all(Array.from(deptSlugs).map(async (slug) => {
        try {
          const res = await fetch(`${cleanBaseUrl}/api/v1/departments/${slug}/teachers`, {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
          });
          if (res.ok) {
            const data = await res.json();
            const arr = findArrayInResponse(data);
            if (arr && arr.length > 0) {
              const tagged = arr.map(t => ({
                ...t,
                department_id: t.department_id || t.departmentId || (slug === 'bba' || slug === 'ba' ? 'dept-ba' : `dept-${slug}`)
              }));
              deptTeachers.push(...tagged);
            }
          }
        } catch (e) {
          // Silent catch for department sub-routes
        }
      }));

      if (deptTeachers.length > 0) {
        rawTeachers = deptTeachers;
      } else {
        const teachResult = await fetchFirstSuccessful(teacherEndpoints);
        rawTeachers = teachResult.data;
      }

      const mappedTeachers: Teacher[] = rawTeachers.map((t: any, idx: number) => {
        // Handle teaching areas
        let areas: any[] = [];
        if (t.teaching_areas && Array.isArray(t.teaching_areas)) {
          areas = t.teaching_areas;
        } else if (t.teachingAreas && Array.isArray(t.teachingAreas)) {
          areas = t.teachingAreas;
        } else if (typeof t.teaching_interest === 'string') {
          areas = t.teaching_interest.split(',').map((s: string) => s.trim());
        }

        // Handle research interests
        let interests: string[] = [];
        if (t.research_interests && Array.isArray(t.research_interests)) {
          interests = t.research_interests;
        } else if (t.researchInterests && Array.isArray(t.researchInterests)) {
          interests = t.researchInterests;
        } else if (typeof t.research_interest === 'string') {
          interests = t.research_interest.split(',').map((s: string) => s.trim());
        }

        return {
          id: String(t.id || t.teacher_id || t.employee_id || `t-${idx}`),
          employee_id: t.employee_id || '',
          name: t.name || `${t.first_name || ''} ${t.last_name || ''}`.trim() || 'Faculty Scholar',
          first_name: t.first_name || '',
          last_name: t.last_name || '',
          avatar: t.photo || t.avatar || '',
          photo: t.photo || t.avatar || '',
          designation: t.designation || 'Lecturer',
          administrativeRole: t.administrative_role || t.administrativeRole || 'None',
          administrative_roles: t.administrative_roles || [],
          departmentId: String(t.department_id || t.departmentId || t.dept_id || ''),
          email: t.email || '',
          phone: t.phone || t.personal_phone || '',
          office: t.office_room || t.office || '',
          office_room: t.office_room || '',
          website: t.website || '',
          linkedin: t.linkedin || t.social_linkedin || '',
          googleScholar: t.google_scholar || t.googleScholar || '',
          researchGate: t.research_gate || t.researchGate || '',
          facebook: t.facebook || t.social_facebook || '',
          instagram: t.instagram || t.social_instagram || '',
          github: t.github || t.social_github || '',
          bio: t.bio || '',
          teachingAreas: areas,
          researchInterests: interests,
          research_interest: t.research_interest || '',
          publications: t.publications || [],
          jobExperiences: t.job_experiences || t.jobExperiences || [],
          trainingExperiences: t.training_experiences || t.trainingExperiences || [],
          awards: t.awards || [],
          memberships: t.memberships || [],
          academicBackground: t.educations || t.academicBackground || [],
          educations: t.educations || [],
          researchProjects: t.research_projects || t.researchProjects || [],
          thesisSupervisions: t.thesis_supervisions || t.thesisSupervisions || [],
          consultancies: t.consultancies || [],
          communityServices: t.community_services || t.communityServices || []
        };
      });

      // Save credentials in local storage
      localStorage.setItem('diu_api_base_url', cleanBaseUrl);
      localStorage.setItem('diu_api_connected', 'true');

      // Forward mapped structures to application
      onDataLoaded({
        teachers: mappedTeachers,
        departments: mappedDepartments,
        faculties: mappedFaculties
      });

      setStats({
        teachers: mappedTeachers.length,
        departments: mappedDepartments.length,
        faculties: mappedFaculties.length
      });
      setStatus('connected');
    } catch (e: any) {
      console.error(e);
      setStatus('error');
      if (isAuto) {
        // Reset silent auto-connect on crash
        localStorage.setItem('diu_api_connected', 'false');
      }
      setErrorMsg(
        `Failed to communicate with your local server. Check that your local backend is running and supports CORS requests.

Error details: ${e.message || 'CORS restriction or network connection refused.'}`
      );
    }
  };

  const handleResetToDemo = () => {
    localStorage.removeItem('diu_api_connected');
    setStatus('idle');
    setStats(null);
    setErrorMsg(null);
    onReset();
  };

  return (
    <div className="fixed bottom-6 right-6 z-50 font-sans" id="local-api-panel">
      {/* Floating Status Indicator Badge */}
      <button
        onClick={() => setIsOpen(prev => !prev)}
        className={`flex items-center gap-2 px-4 py-3 rounded-2xl shadow-xl transition-all active:scale-95 border cursor-pointer ${
          status === 'connected'
            ? 'bg-emerald-600 hover:bg-emerald-700 text-white border-emerald-500'
            : status === 'error'
            ? 'bg-red-600 hover:bg-red-700 text-white border-red-500'
            : 'bg-slate-800 hover:bg-slate-900 text-white border-slate-700'
        }`}
      >
        <Database className={`w-4 h-4 ${status === 'loading' ? 'animate-spin' : ''}`} />
        <span className="text-xs font-bold uppercase tracking-wider">
          {status === 'connected' ? 'Live API Connected' : status === 'error' ? 'API Error' : 'Local API Manager'}
        </span>
        <span className="relative flex h-2 w-2">
          <span className={`animate-ping absolute inline-flex h-full w-full rounded-full opacity-75 ${status === 'connected' ? 'bg-emerald-400' : status === 'error' ? 'bg-red-400' : 'bg-amber-400'}`}></span>
          <span className={`relative inline-flex rounded-full h-2 w-2 ${status === 'connected' ? 'bg-emerald-400' : status === 'error' ? 'bg-red-500' : 'bg-amber-500'}`}></span>
        </span>
      </button>

      {/* Connection Console Drawer */}
      {isOpen && (
        <div className="absolute bottom-16 right-0 w-80 md:w-96 bg-white/95 backdrop-blur-md rounded-2xl border border-slate-200/80 shadow-2xl p-5 overflow-hidden ring-1 ring-slate-900/5 transition-all">
          <div className="flex items-center justify-between pb-3.5 border-b border-slate-100">
            <div className="flex items-center gap-2">
              <Settings className="w-5 h-5 text-diu-green" />
              <div>
                <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wider">Local Server Console</h4>
                <p className="text-[10px] text-slate-400 font-medium font-sans">Bridge local development APIs in real-time</p>
              </div>
            </div>
            <button 
              onClick={() => setIsOpen(false)}
              className="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-50 transition-colors cursor-pointer"
            >
              <X className="w-4 h-4" />
            </button>
          </div>

          <div className="py-4 space-y-4">
            <div>
              <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">
                Local API Base URL
              </label>
              <div className="flex gap-2">
                <input
                  type="text"
                  placeholder="e.g. http://localhost:8000"
                  value={baseUrl}
                  onChange={(e) => setBaseUrl(e.target.value)}
                  className="flex-1 text-xs px-3 py-2 border border-slate-200 rounded-xl bg-white focus:outline-none focus:ring-1 focus:ring-diu-green focus:border-diu-green font-mono"
                />
                <button
                  onClick={() => handleConnect()}
                  disabled={status === 'loading'}
                  className="bg-diu-green hover:bg-diu-green-hover text-white text-xs font-bold px-3 py-2 rounded-xl transition-all disabled:opacity-50 shrink-0 cursor-pointer flex items-center gap-1"
                >
                  {status === 'loading' ? (
                    <RefreshCw className="w-3.5 h-3.5 animate-spin" />
                  ) : (
                    <Wifi className="w-3.5 h-3.5" />
                  )}
                  <span>Connect</span>
                </button>
              </div>
            </div>

            {/* Quick Presets */}
            <div className="flex items-center gap-1.5 flex-wrap">
              <span className="text-[9px] text-slate-400 font-bold uppercase">Presets:</span>
              {['http://localhost:8000', 'http://localhost:3000', 'http://127.0.0.1:8000'].map(preset => (
                <button
                  key={preset}
                  onClick={() => setBaseUrl(preset)}
                  className="text-[9px] font-mono px-2 py-0.5 rounded bg-slate-50 hover:bg-slate-100 border border-slate-200/60 text-slate-500 cursor-pointer"
                >
                  {preset.replace('http://', '')}
                </button>
              ))}
            </div>

            {/* Status Feedback Block */}
            {status === 'connected' && stats && (
              <div className="p-3.5 bg-emerald-50 border border-emerald-100 rounded-xl space-y-2">
                <div className="flex items-center gap-2 text-emerald-800 text-xs font-bold">
                  <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
                  <span>Successfully Synced live!</span>
                </div>
                <div className="grid grid-cols-3 gap-2 text-center">
                  <div className="p-2 bg-white rounded-lg border border-emerald-100 shadow-3xs">
                    <p className="text-[9px] text-slate-400 font-bold uppercase leading-none">Scholars</p>
                    <p className="text-sm font-bold text-emerald-700 mt-1">{stats.teachers}</p>
                  </div>
                  <div className="p-2 bg-white rounded-lg border border-emerald-100 shadow-3xs">
                    <p className="text-[9px] text-slate-400 font-bold uppercase leading-none">Depts</p>
                    <p className="text-sm font-bold text-emerald-700 mt-1">{stats.departments}</p>
                  </div>
                  <div className="p-2 bg-white rounded-lg border border-emerald-100 shadow-3xs">
                    <p className="text-[9px] text-slate-400 font-bold uppercase leading-none">Faculties</p>
                    <p className="text-sm font-bold text-emerald-700 mt-1">{stats.faculties}</p>
                  </div>
                </div>
              </div>
            )}

            {status === 'error' && errorMsg && (
              <div className="p-3.5 bg-red-50 border border-red-100 rounded-xl space-y-1.5">
                <div className="flex items-center gap-2 text-red-800 text-xs font-bold">
                  <AlertCircle className="w-4 h-4 text-red-600 shrink-0" />
                  <span>Connection Failed</span>
                </div>
                <p className="text-[10px] text-red-600 font-sans font-medium leading-relaxed whitespace-pre-line">
                  {errorMsg}
                </p>
                <div className="text-[9px] text-slate-400 font-sans leading-snug pt-1">
                  <strong>Fixing CORS in Node/Express:</strong><br />
                  <code className="bg-slate-100 p-0.5 rounded font-mono block mt-1">
                    app.use(require('cors')({`{origin: "*"}`}));
                  </code>
                </div>
              </div>
            )}

            {status === 'idle' && (
              <div className="p-3 bg-slate-50 border border-slate-200/60 rounded-xl text-[10px] text-slate-500 font-sans leading-relaxed flex gap-2 items-start">
                <Info className="w-3.5 h-3.5 text-slate-400 shrink-0 mt-0.5" />
                <div>
                  Currently viewing the local <strong>Static Demo Database ({currentTeachersCount} Scholars)</strong>. Enter your local endpoint above and click Connect to load custom live data.
                </div>
              </div>
            )}
          </div>

          <div className="pt-3 border-t border-slate-100 flex justify-between items-center text-xs">
            {status === 'connected' ? (
              <button
                onClick={handleResetToDemo}
                className="text-slate-500 hover:text-slate-800 font-bold font-sans underline cursor-pointer"
              >
                Disconnect & Use Demo
              </button>
            ) : (
              <span className="text-[10px] text-slate-400 font-semibold uppercase font-sans">Ready to bridge</span>
            )}
            <button
              onClick={() => setIsOpen(false)}
              className="px-3 py-1.5 bg-slate-100 text-slate-600 rounded-lg text-[10px] font-bold uppercase tracking-wider hover:bg-slate-200 transition-colors cursor-pointer"
            >
              Close Console
            </button>
          </div>
        </div>
      )}
    </div>
  );
};
