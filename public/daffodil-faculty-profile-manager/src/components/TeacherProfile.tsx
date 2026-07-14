import React, { useState } from 'react';
import { Teacher, Publication } from '../types';
import { 
  Mail, Phone, MapPin, Globe, Linkedin, GraduationCap, 
  BookOpen, Award as AwardIcon, Briefcase, ChevronRight,
  ArrowLeft, Compass, Bookmark, Plus, Trash2, Calendar,
  Facebook, Instagram, Github, Heart, Shield, Users, Building
} from 'lucide-react';

interface TeacherProfileProps {
  teacher: Teacher;
  departmentName: string;
  facultyName: string;
  onBack: () => void;
  onSelectPublication: (publication: Publication) => void;
  isOwnProfile?: boolean;
  onUpdateTeacher?: (updatedTeacher: Teacher) => void;
}

export const TeacherProfile: React.FC<TeacherProfileProps> = ({
  teacher,
  departmentName,
  facultyName,
  onBack,
  onSelectPublication,
  isOwnProfile = false,
  onUpdateTeacher
}) => {
  const [activeTab, setActiveTab] = useState<
    | 'overview'
    | 'academic'
    | 'publications'
    | 'experience'
    | 'projects'
    | 'supervisions'
    | 'training'
    | 'awards'
    | 'memberships'
    | 'consultancy'
    | 'admin_roles'
    | 'community'
  >('overview');
  const [newArea, setNewArea] = useState('');
  const [newInterest, setNewInterest] = useState('');

  // Safe helper variables for robust format mapping
  const displayName = teacher.name || `${teacher.first_name || ''} ${teacher.last_name || ''}`.trim() || 'Scholar';
  const displayAvatar = teacher.avatar || teacher.photo || '';
  const displayOffice = teacher.office || teacher.office_room || 'N/A';
  const displayPhone = teacher.phone || teacher.personal_phone || 'N/A';
  const displayEmail = teacher.email || 'N/A';

  const getSocialLink = (platform: string, fallbackUrl?: string) => {
    if (teacher.social_links && teacher.social_links.length > 0) {
      const found = teacher.social_links.find(s => s.platform.toLowerCase() === platform.toLowerCase());
      if (found) return found.url;
    }
    return fallbackUrl;
  };

  const displayWebsite = getSocialLink('website', teacher.website);
  const displayLinkedin = getSocialLink('linkedin', teacher.linkedin);
  const displayGoogleScholar = getSocialLink('google scholar', teacher.googleScholar);
  const displayResearchGate = getSocialLink('researchgate', teacher.researchGate);
  const displayFacebook = getSocialLink('facebook', teacher.facebook);
  const displayGithub = getSocialLink('github', teacher.github);
  const displayInstagram = getSocialLink('instagram', teacher.instagram);

  const displayAdminRole = teacher.administrativeRole || 
    (teacher.administrative_roles && teacher.administrative_roles.length > 0 
      ? (typeof teacher.administrative_roles[0] === 'string' 
          ? teacher.administrative_roles[0] 
          : teacher.administrative_roles[0].name)
      : '');
  const hasAdminRole = !!displayAdminRole && displayAdminRole !== 'None';

  const displayBio = teacher.bio || teacher.research_interest || '';

  const listTeachingAreas = teacher.teachingAreas || [];
  const listResearchInterests = teacher.researchInterests || 
    (teacher.research_interest 
      ? teacher.research_interest.split(',').map(s => s.trim()).filter(Boolean) 
      : []);

  const listEducations = teacher.educations || teacher.academicBackground || [];
  const listExperiences = teacher.jobExperiences || [];

  const getExpDuration = (exp: any) => {
    if (exp.duration) return exp.duration;
    const start = exp.start_date ? new Date(exp.start_date).getFullYear() : '';
    const end = exp.is_current ? 'Present' : (exp.end_date ? new Date(exp.end_date).getFullYear() : 'Past');
    if (start && end) return `${start} - ${end}`;
    return start || end || 'N/A';
  };

  const handleUpdateBio = (newBio: string) => {
    if (onUpdateTeacher) {
      onUpdateTeacher({
        ...teacher,
        bio: newBio
      });
    }
  };

  const handleAddArea = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newArea.trim()) return;
    if (onUpdateTeacher) {
      onUpdateTeacher({
        ...teacher,
        teachingAreas: [...(teacher.teachingAreas || []), newArea.trim()]
      });
      setNewArea('');
    }
  };

  const handleRemoveArea = (idx: number) => {
    if (onUpdateTeacher) {
      const updated = [...(teacher.teachingAreas || [])];
      updated.splice(idx, 1);
      onUpdateTeacher({
        ...teacher,
        teachingAreas: updated
      });
    }
  };

  const handleAddInterest = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newInterest.trim()) return;
    if (onUpdateTeacher) {
      onUpdateTeacher({
        ...teacher,
        researchInterests: [...(teacher.researchInterests || []), newInterest.trim()]
      });
      setNewInterest('');
    }
  };

  const handleRemoveInterest = (idx: number) => {
    if (onUpdateTeacher) {
      const updated = [...(teacher.researchInterests || [])];
      updated.splice(idx, 1);
      onUpdateTeacher({
        ...teacher,
        researchInterests: updated
      });
    }
  };

  return (
    <div className="bg-white/40 backdrop-blur-md rounded-2xl border border-white/60 shadow-sm overflow-hidden" id={`teacher-profile-${teacher.id}`}>
      
      {/* Cover / Hero header banner */}
      <div className="relative h-48 bg-gradient-to-r from-diu-green-dark via-diu-green to-diu-orange/80 p-6 md:p-8 flex items-end border-b border-white/20">
        {/* Back Button */}
        <button 
          onClick={onBack}
          className="absolute top-4 left-4 bg-white/20 hover:bg-white/30 text-white text-xs font-semibold px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition-all backdrop-blur-xs cursor-pointer animate-fade-in"
        >
          <ArrowLeft className="w-3.5 h-3.5" />
          Back to list
        </button>

        {/* Quick Decorative Accent */}
        <div className="absolute right-6 top-6 text-white/10 font-display font-extrabold text-7xl select-none hidden sm:block">
          DIU
        </div>
      </div>

      {/* Main Info Frame */}
      <div className="px-6 md:px-8 pb-8 relative">
        
        {/* Profile Avatar shifted on top of cover */}
        <div className="flex flex-col md:flex-row md:items-end justify-between -mt-16 mb-6 gap-4">
          <div className="flex flex-col md:flex-row items-center md:items-end gap-5 text-center md:text-left">
            <div className="w-32 h-32 rounded-2xl overflow-hidden border-4 border-white shadow-lg bg-slate-100 shrink-0">
              {displayAvatar ? (
                <img src={displayAvatar} alt={displayName} className="w-full h-full object-cover" />
              ) : (
                <div className="w-full h-full bg-diu-green text-white flex items-center justify-center font-display font-bold text-4xl">
                  {displayName.split(' ').slice(-1)[0]?.[0] || 'S'}
                </div>
              )}
            </div>
            
            <div className="pt-2">
              <div className="flex flex-wrap items-center justify-center md:justify-start gap-2 mb-1.5">
                {hasAdminRole && (
                  <span className="bg-diu-orange text-white text-[10px] font-sans font-bold uppercase px-2.5 py-0.5 rounded-sm shadow-xs border border-diu-orange/20">
                    {displayAdminRole}
                  </span>
                )}
                <span className="bg-white/60 text-slate-700 text-[10px] font-sans font-bold uppercase px-2.5 py-0.5 rounded-sm border border-white/80">
                  {teacher.designation}
                </span>
              </div>
              <h2 className="text-xl md:text-2xl font-display font-bold text-slate-900 tracking-tight leading-tight">
                {displayName}
              </h2>
              <p className="text-xs text-slate-500 font-sans font-medium mt-1">
                {departmentName} • <span className="text-slate-400">{facultyName}</span>
              </p>
            </div>
          </div>

          {/* Social / Scholars Web Profile Buttons */}
          <div className="flex flex-wrap items-center justify-center gap-2">
            {displayWebsite && (
              <a 
                href={displayWebsite} 
                target="_blank" 
                rel="noopener noreferrer" 
                className="p-2 bg-white/40 hover:bg-white/80 text-slate-600 rounded-lg border border-white/60 transition-colors shadow-2xs"
                title="Website"
              >
                <Globe className="w-4 h-4" />
              </a>
            )}
            {displayLinkedin && (
              <a 
                href={displayLinkedin} 
                target="_blank" 
                rel="noopener noreferrer" 
                className="p-2 bg-blue-50/40 hover:bg-blue-100/60 text-blue-600 rounded-lg border border-blue-200/50 transition-colors shadow-2xs"
                title="LinkedIn"
              >
                <Linkedin className="w-4 h-4" />
              </a>
            )}
            {displayGoogleScholar && (
              <a 
                href={displayGoogleScholar} 
                target="_blank" 
                rel="noopener noreferrer" 
                className="p-2 bg-red-50/40 hover:bg-red-100/60 text-red-600 rounded-lg border border-red-200/50 transition-colors shadow-2xs"
                title="Google Scholar"
              >
                <GraduationCap className="w-4 h-4 text-red-600" />
              </a>
            )}
            {displayResearchGate && (
              <a 
                href={displayResearchGate} 
                target="_blank" 
                rel="noopener noreferrer" 
                className="px-2.5 py-1.5 bg-emerald-50/40 hover:bg-emerald-100/60 text-emerald-600 rounded-lg border border-emerald-200/50 transition-colors shadow-2xs text-xs font-black tracking-tighter flex items-center justify-center"
                title="ResearchGate"
              >
                <span className="font-sans font-black text-xs leading-none">RG</span>
              </a>
            )}
            {displayFacebook && (
              <a 
                href={displayFacebook} 
                target="_blank" 
                rel="noopener noreferrer" 
                className="p-2 bg-blue-50/40 hover:bg-blue-100/60 text-blue-700 rounded-lg border border-blue-200/50 transition-colors shadow-2xs"
                title="Facebook"
              >
                <Facebook className="w-4 h-4" />
              </a>
            )}
            {displayInstagram && (
              <a 
                href={displayInstagram} 
                target="_blank" 
                rel="noopener noreferrer" 
                className="p-2 bg-pink-50/40 hover:bg-pink-100/60 text-pink-600 rounded-lg border border-pink-200/50 transition-colors shadow-2xs"
                title="Instagram"
              >
                <Instagram className="w-4 h-4" />
              </a>
            )}
            {displayGithub && (
              <a 
                href={displayGithub} 
                target="_blank" 
                rel="noopener noreferrer" 
                className="p-2 bg-slate-50/40 hover:bg-slate-100/60 text-slate-800 rounded-lg border border-slate-200/50 transition-colors shadow-2xs"
                title="GitHub"
              >
                <Github className="w-4 h-4" />
              </a>
            )}
          </div>
        </div>

        {/* Contact Strip */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 py-4 px-5 bg-white/30 backdrop-blur-xs rounded-xl border border-white/60 mb-8 text-xs text-slate-600 font-sans ring-1 ring-slate-900/5">
          <div className="flex items-center gap-2.5">
            <Mail className="w-4 h-4 text-diu-green shrink-0" />
            <div className="min-w-0">
              <p className="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Email Address</p>
              <p className="font-mono truncate font-semibold text-slate-700">{displayEmail}</p>
            </div>
          </div>
          <div className="flex items-center gap-2.5">
            <Phone className="w-4 h-4 text-diu-green shrink-0" />
            <div>
              <p className="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Contact Number</p>
              <p className="font-semibold text-slate-700">{displayPhone}</p>
            </div>
          </div>
          <div className="flex items-center gap-2.5">
            <MapPin className="w-4 h-4 text-diu-green shrink-0" />
            <div className="min-w-0">
              <p className="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Office Location</p>
              <p className="font-semibold text-slate-700 truncate">{displayOffice}</p>
            </div>
          </div>
        </div>

        {/* Tab Controls */}
        <div className="flex border-b border-white/40 mb-6 overflow-x-auto gap-1 pb-1 scrollbar-thin scrollbar-thumb-slate-200 scrollbar-track-transparent">
          {[
            { id: 'overview', label: 'Overview', icon: Compass },
            { id: 'academic', label: 'Academic Background', icon: GraduationCap },
            { id: 'publications', label: `Publications (${teacher.publications?.length || 0})`, icon: BookOpen },
            { id: 'experience', label: 'Employment History', icon: Briefcase },
            { id: 'projects', label: `Projects & Grants (${teacher.researchProjects?.length || 0})`, icon: Compass },
            { id: 'supervisions', label: `Thesis Advising (${teacher.thesisSupervisions?.length || 0})`, icon: Users },
            { id: 'training', label: `Special Trainings (${teacher.trainingExperiences?.length || 0})`, icon: GraduationCap },
            { id: 'awards', label: `Awards (${teacher.awards?.length || 0})`, icon: AwardIcon },
            { id: 'memberships', label: `Memberships (${teacher.memberships?.length || 0})`, icon: Bookmark },
            { id: 'consultancy', label: `Industry Consultancies (${teacher.consultancies?.length || 0})`, icon: Building },
            { id: 'admin_roles', label: 'Administrative Roles', icon: Shield },
            { id: 'community', label: `Community Services (${teacher.communityServices?.length || 0})`, icon: Heart }
          ].map(tab => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id as any)}
              className={`px-4 py-3 text-xs font-semibold whitespace-nowrap transition-all flex items-center gap-2 border-b-2 -mb-px cursor-pointer ${
                activeTab === tab.id
                  ? 'border-diu-green text-diu-green font-bold bg-white/40 rounded-t-lg'
                  : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-white/10'
              }`}
            >
              <tab.icon className="w-4 h-4 shrink-0" />
              {tab.label}
            </button>
          ))}
        </div>

        {/* TAB CONTENTS */}
        
        {/* 1. Overview */}
        {activeTab === 'overview' && (
          <div className="space-y-6">
            {isOwnProfile && (
              <div className="bg-amber-50/60 border border-amber-200/60 rounded-xl p-3.5 mb-2 flex items-center gap-3 animate-pulse">
                <div className="w-2 h-2 rounded-full bg-amber-500 shrink-0" />
                <p className="text-xs text-amber-800 font-sans font-semibold">
                  <strong>Profile Edit Mode Active</strong>: You are viewing your own profile. You can modify your biography, teaching areas, and research interests below in real-time.
                </p>
              </div>
            )}

            <div>
              <div className="flex items-center justify-between mb-2">
                <h3 className="text-sm font-bold text-slate-800 uppercase tracking-wider">Biography</h3>
                {isOwnProfile && (
                  <span className="text-[10px] text-slate-400 font-bold font-mono">EDITABLE BIOGRAPHY</span>
                )}
              </div>
              {isOwnProfile ? (
                <textarea
                  value={teacher.bio || ''}
                  onChange={(e) => handleUpdateBio(e.target.value)}
                  placeholder="Describe your academic credentials, focus, and research story..."
                  className="w-full text-sm text-slate-700 bg-white border border-slate-200 rounded-xl p-3 focus:outline-none focus:ring-2 focus:ring-diu-green focus:border-diu-green min-h-[120px]"
                />
              ) : (
                <p className="text-sm text-slate-600 leading-relaxed font-sans">{displayBio || "No biography added yet."}</p>
              )}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
              {/* Teaching Areas Block */}
              <div className="bg-white/30 backdrop-blur-xs p-5 rounded-xl border border-white/60 ring-1 ring-slate-900/5">
                <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                  <Bookmark className="w-3.5 h-3.5 text-diu-green" />
                  Teaching Areas
                </h4>

                {isOwnProfile && (
                  <form onSubmit={handleAddArea} className="flex gap-2 mb-3">
                    <input
                      type="text"
                      placeholder="Add new teaching subject..."
                      value={newArea}
                      onChange={(e) => setNewArea(e.target.value)}
                      className="flex-1 text-xs px-3 py-1.5 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-diu-green"
                    />
                    <button
                      type="submit"
                      className="bg-diu-green hover:bg-diu-green-hover text-white p-1.5 rounded-lg flex items-center justify-center transition-colors shrink-0 cursor-pointer"
                    >
                      <Plus className="w-4 h-4" />
                    </button>
                  </form>
                )}

                {listTeachingAreas.length > 0 ? (
                  <ul className="space-y-2">
                    {listTeachingAreas.map((areaItem, idx) => {
                      const areaName = typeof areaItem === 'string' ? areaItem : areaItem.area;
                      return (
                        <li key={idx} className="flex items-center justify-between text-xs text-slate-600 font-sans group">
                          <span className="flex items-center gap-2">
                            <ChevronRight className="w-3 h-3 text-diu-orange shrink-0" />
                            {areaName}
                          </span>
                          {isOwnProfile && (
                            <button
                              onClick={() => handleRemoveArea(idx)}
                              className="text-red-400 hover:text-red-600 opacity-0 group-hover:opacity-100 transition-opacity p-0.5 cursor-pointer"
                              title="Remove"
                            >
                              <Trash2 className="w-3.5 h-3.5" />
                            </button>
                          )}
                        </li>
                      );
                    })}
                  </ul>
                ) : (
                  <p className="text-xs text-slate-400">No teaching areas specified.</p>
                )}
              </div>

              {/* Research Interests Block */}
              <div className="bg-white/30 backdrop-blur-xs p-5 rounded-xl border border-white/60 ring-1 ring-slate-900/5">
                <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                  <Compass className="w-3.5 h-3.5 text-diu-green" />
                  Research Interests
                </h4>

                {isOwnProfile && (
                  <form onSubmit={handleAddInterest} className="flex gap-2 mb-3">
                    <input
                      type="text"
                      placeholder="Add research domain (e.g. AI, NLP)..."
                      value={newInterest}
                      onChange={(e) => setNewInterest(e.target.value)}
                      className="flex-1 text-xs px-3 py-1.5 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-diu-green"
                    />
                    <button
                      type="submit"
                      className="bg-diu-green hover:bg-diu-green-hover text-white p-1.5 rounded-lg flex items-center justify-center transition-colors shrink-0 cursor-pointer"
                    >
                      <Plus className="w-4 h-4" />
                    </button>
                  </form>
                )}

                {listResearchInterests.length > 0 ? (
                  <div className="flex flex-wrap gap-2">
                    {listResearchInterests.map((interest, idx) => (
                      <span 
                        key={idx} 
                        className="bg-white/60 border border-white/80 text-slate-700 text-xs font-sans px-3 py-1 rounded-full shadow-2xs flex items-center gap-1"
                      >
                        {interest}
                        {isOwnProfile && (
                          <button
                            onClick={() => handleRemoveInterest(idx)}
                            className="text-slate-400 hover:text-red-500 font-bold ml-1 text-[10px] cursor-pointer"
                            title="Remove"
                          >
                            ×
                          </button>
                        )}
                      </span>
                    ))}
                  </div>
                ) : (
                  <p className="text-xs text-slate-400">No research interests listed.</p>
                )}
              </div>
            </div>
          </div>
        )}

        {/* 2. Publications */}
        {activeTab === 'publications' && (
          <div className="space-y-4" id="publications-tab-list">
            <div className="flex justify-between items-center pb-2">
              <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider">List of Scholarly Papers</h3>
              <span className="text-[10px] text-slate-400 font-semibold font-mono">
                Click any publication to view its full abstract and citations
              </span>
            </div>
            
            {teacher.publications && teacher.publications.length > 0 ? (
              <div className="space-y-3">
                {teacher.publications.map((pub) => {
                  const pubYear = pub.year || pub.publication_year;
                  const pubVenue = pub.venue || pub.journal_name;
                  const pubType = pub.type || pub.paper_type || 'Paper';
                  return (
                    <div 
                      key={pub.id}
                      onClick={() => onSelectPublication(pub)}
                      className="p-4 rounded-xl border border-white/60 hover:border-diu-green/40 bg-white/30 backdrop-blur-xs shadow-3xs hover:shadow-xs transition-all cursor-pointer group flex items-start gap-4"
                    >
                      <div className="bg-diu-green/10 text-diu-green p-2.5 rounded-lg shrink-0 mt-0.5">
                        <BookOpen className="w-5 h-5" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 mb-1">
                          <span className={`text-[9px] font-sans font-bold px-1.5 py-0.5 rounded-xs ${
                            pubType.toLowerCase().includes('journal') 
                              ? 'bg-emerald-50 text-emerald-700 border border-emerald-100'
                              : 'bg-indigo-50 text-indigo-700 border border-indigo-100'
                          }`}>
                            {pubType}
                          </span>
                          <span className="text-[10px] text-slate-400 font-semibold font-sans">{pubYear}</span>
                          {pub.citations !== undefined && (
                            <span className="text-[10px] text-slate-500 font-medium ml-auto bg-white/60 border border-white/80 px-2 py-0.5 rounded-sm font-mono">
                              Citations: {pub.citations}
                            </span>
                          )}
                        </div>
                        <h4 className="text-sm font-semibold text-slate-800 tracking-tight leading-snug line-clamp-2 group-hover:text-diu-green transition-colors">
                          {pub.title}
                        </h4>
                        <p className="text-xs text-slate-500 mt-1 italic font-sans">{pub.authors}</p>
                        <p className="text-xs text-slate-400 font-sans mt-0.5 font-medium">{pubVenue}</p>
                      </div>
                    </div>
                  );
                })}
              </div>
            ) : (
              <div className="text-center py-12 border-2 border-dashed border-white/60 rounded-xl bg-white/10">
                <BookOpen className="w-10 h-10 text-slate-400 mx-auto mb-2" />
                <p className="text-sm text-slate-500 font-sans font-medium">No publications added yet for this teacher.</p>
              </div>
            )}
          </div>
        )}

        {/* 3. Experience */}
        {activeTab === 'experience' && (
          <div className="space-y-4">
            <h3 className="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
              <Briefcase className="w-4 h-4 text-diu-green" />
              Employment History
            </h3>
            {listExperiences.length > 0 ? (
              <div className="relative border-l border-white/40 pl-5 ml-2.5 space-y-6">
                {listExperiences.map((exp) => {
                  const duration = getExpDuration(exp);
                  const role = exp.role || exp.position || 'Faculty';
                  const inst = exp.institution || exp.organization || 'DIU';
                  return (
                    <div key={exp.id} className="relative">
                      {/* Timeline Dot */}
                      <span className="absolute -left-7.5 top-1 bg-white border-2 border-diu-green rounded-full w-4.5 h-4.5 flex items-center justify-center shadow-xs">
                        <span className="w-1.5 h-1.5 bg-diu-green rounded-full" />
                      </span>
                      <div>
                        <div className="flex items-center gap-2 text-xs font-bold text-diu-green tracking-wide">
                          <Calendar className="w-3.5 h-3.5 text-diu-green shrink-0" />
                          {duration}
                        </div>
                        <h4 className="text-sm font-bold text-slate-800 mt-1 font-display">{role}</h4>
                        <p className="text-xs text-slate-500 font-semibold mt-0.5">{inst}</p>
                        {exp.description && (
                          <p className="text-xs text-slate-500 font-sans mt-1 leading-relaxed">{exp.description}</p>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            ) : (
              <p className="text-xs text-slate-400">No corporate or academic work history submitted.</p>
            )}
          </div>
        )}

        {/* 4. Academic Background */}
        {activeTab === 'academic' && (
          <div className="space-y-4">
            <h3 className="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
              <GraduationCap className="w-4 h-4 text-diu-green" />
              Academic Degrees & Background
            </h3>
            {listEducations.length > 0 ? (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {listEducations.map((deg: any, idx) => {
                  const year = deg.year || deg.passing_year;
                  const degree = deg.degree || deg.degree_type;
                  const result = deg.result || deg.grade;
                  return (
                    <div key={idx} className="p-4 rounded-xl border border-white/60 bg-white/30 backdrop-blur-xs ring-1 ring-slate-900/5">
                      <span className="bg-diu-green/10 text-diu-green text-[9px] font-sans font-black uppercase px-2 py-0.5 rounded-xs">
                        Year: {year}
                      </span>
                      <h4 className="text-sm font-bold text-slate-800 mt-2 font-display">{degree}</h4>
                      <p className="text-xs text-slate-600 mt-0.5 font-medium">{deg.institution}</p>
                      {result && (
                        <p className="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-2 bg-slate-50 border border-slate-100 rounded-sm inline-block px-1.5 py-0.5">
                          Result: {result}
                        </p>
                      )}
                    </div>
                  );
                })}
              </div>
            ) : (
              <div className="p-4 rounded-xl border border-white/60 bg-white/30 backdrop-blur-xs">
                <p className="text-xs text-slate-500 font-medium">B.Sc. & M.Sc. in Engineering / relevant discipline from Daffodil International University / reputable public university.</p>
              </div>
            )}
          </div>
        )}

        {/* 5. Research Projects */}
        {activeTab === 'projects' && (
          <div className="space-y-4">
            <h3 className="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
              <Compass className="w-4 h-4 text-diu-green" />
              Funded Research Projects & Grants
            </h3>
            {teacher.researchProjects && teacher.researchProjects.length > 0 ? (
              <div className="space-y-3">
                {teacher.researchProjects.map((proj) => (
                  <div key={proj.id} className="p-4 rounded-xl border border-emerald-100 bg-emerald-50/10 backdrop-blur-xs flex gap-3.5 items-start">
                    <div className="bg-emerald-50 text-emerald-600 p-2 rounded-lg shrink-0 border border-emerald-100 shadow-3xs">
                      <Compass className="w-5 h-5" />
                    </div>
                    <div className="flex-1">
                      <div className="flex items-center gap-2 mb-1">
                        <span className={`text-[9px] font-sans font-bold px-1.5 py-0.5 rounded-xs ${
                          proj.status === 'Ongoing' ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800'
                        }`}>
                          {proj.status}
                        </span>
                        <span className="text-[10px] text-slate-400 font-bold uppercase">{proj.duration}</span>
                      </div>
                      <h4 className="text-xs font-bold text-slate-800 mt-1 leading-snug font-display">{proj.title}</h4>
                      <p className="text-[11px] text-slate-500 font-semibold mt-0.5">Role: <span className="text-slate-700">{proj.role}</span></p>
                      {proj.fundingBody && (
                        <p className="text-[10px] text-slate-400 font-medium mt-1">Sponsor: {proj.fundingBody} {proj.amount && `• Budget: ${proj.amount}`}</p>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-xs text-slate-400">No ongoing or completed research grants on record.</p>
            )}
          </div>
        )}

        {/* 6. Thesis Advising */}
        {activeTab === 'supervisions' && (
          <div className="space-y-4">
            <h3 className="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
              <Users className="w-4 h-4 text-diu-green" />
              Academic Thesis Advising & Student Supervisions
            </h3>
            {teacher.thesisSupervisions && teacher.thesisSupervisions.length > 0 ? (
              <div className="space-y-3">
                {teacher.thesisSupervisions.map((sup) => (
                  <div key={sup.id} className="p-4 rounded-xl border border-blue-100 bg-blue-50/10 backdrop-blur-xs flex gap-3.5 items-start">
                    <div className="bg-blue-50 text-blue-600 p-2 rounded-lg shrink-0 border border-blue-100 shadow-3xs">
                      <Users className="w-5 h-5" />
                    </div>
                    <div className="flex-1">
                      <div className="flex items-center gap-2 mb-1">
                        <span className="bg-blue-100 text-blue-800 text-[9px] font-sans font-bold px-1.5 py-0.5 rounded-xs">
                          {sup.program} Program
                        </span>
                        <span className="text-[10px] text-slate-400 font-bold uppercase">Year: {sup.year}</span>
                      </div>
                      <h4 className="text-xs font-bold text-slate-800 mt-1 leading-snug font-display">{sup.title}</h4>
                      <p className="text-[11px] text-slate-500 font-semibold mt-0.5">Student Name: <span className="text-slate-700">{sup.studentName}</span></p>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-xs text-slate-400">No student supervisions on record.</p>
            )}
          </div>
        )}

        {/* 7. Special Trainings */}
        {activeTab === 'training' && (
          <div className="space-y-4">
            <h3 className="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
              <GraduationCap className="w-4 h-4 text-diu-green" />
              Special Training & Pedagogy Programs
            </h3>
            {teacher.trainingExperiences && teacher.trainingExperiences.length > 0 ? (
              <div className="space-y-3">
                {teacher.trainingExperiences.map((trn) => (
                  <div key={trn.id} className="p-4 rounded-xl border border-white/60 bg-white/30 backdrop-blur-xs flex gap-3 ring-1 ring-slate-900/5">
                    <div className="bg-diu-orange/15 text-diu-orange p-2 rounded-lg shrink-0 h-9 w-9 flex items-center justify-center">
                      <GraduationCap className="w-5 h-5" />
                    </div>
                    <div>
                      <h4 className="text-xs font-bold text-slate-800 leading-snug font-display">{trn.title}</h4>
                      <p className="text-[11px] text-slate-500 font-semibold mt-0.5">{trn.organization}</p>
                      <div className="flex items-center gap-4 mt-2 text-[10px] text-slate-400 font-bold uppercase">
                        <span>Year: {trn.year}</span>
                        {trn.duration && <span>• Duration: {trn.duration}</span>}
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-xs text-slate-400">No training experiences recorded.</p>
            )}
          </div>
        )}

        {/* 8. Awards & Honors */}
        {activeTab === 'awards' && (
          <div className="space-y-4">
            <h3 className="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
              <AwardIcon className="w-4 h-4 text-diu-orange" />
              Special Awards, Fellowships & Scholarships
            </h3>
            {teacher.awards && teacher.awards.length > 0 ? (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {teacher.awards.map((award) => {
                  const cat = award.category || award.type || 'Award';
                  const org = award.organization || award.awarding_body;
                  return (
                    <div key={award.id} className="p-4 rounded-xl border border-diu-orange/20 bg-diu-orange/5 backdrop-blur-xs flex gap-3.5 items-start">
                      <div className="bg-white text-diu-orange p-2 rounded-lg shrink-0 border border-diu-orange/10 shadow-3xs">
                        <AwardIcon className="w-5 h-5" />
                      </div>
                      <div>
                        <span className="bg-diu-orange text-white text-[8px] font-sans font-bold uppercase px-1.5 py-0.5 rounded-xs">
                          {cat} Award
                        </span>
                        <h4 className="text-xs font-bold text-slate-800 mt-1.5 leading-snug font-display">{award.title}</h4>
                        <p className="text-[11px] text-slate-500 font-semibold mt-0.5">{org} • {award.year}</p>
                      </div>
                    </div>
                  );
                })}
              </div>
            ) : (
              <p className="text-xs text-slate-400">No special awards or achievements documented.</p>
            )}
          </div>
        )}

        {/* 9. Professional Memberships */}
        {activeTab === 'memberships' && (
          <div className="space-y-4">
            <h3 className="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
              <Bookmark className="w-4 h-4 text-diu-green" />
              Professional Memberships & Affiliations
            </h3>
            {teacher.memberships && teacher.memberships.length > 0 ? (
              <div className="space-y-2">
                {teacher.memberships.map((membership, idx) => {
                  const mText = typeof membership === 'string'
                    ? membership
                    : `${membership.organization}${membership.membership_type ? ` (${membership.membership_type})` : ''}${membership.position ? ` - ${membership.position}` : ''}`;
                  return (
                    <div key={idx} className="p-3 bg-white/30 backdrop-blur-xs rounded-xl border border-white/60 flex items-center gap-2.5 text-xs text-slate-700 font-sans font-medium ring-1 ring-slate-900/5">
                      <div className="w-2 h-2 rounded-full bg-diu-green shrink-0" />
                      {mText}
                    </div>
                  );
                })}
              </div>
            ) : (
              <p className="text-xs text-slate-400 font-sans">No affiliated professional bodies declared.</p>
            )}
          </div>
        )}

        {/* 10. Industry Consultancies */}
        {activeTab === 'consultancy' && (
          <div className="space-y-4">
            <h3 className="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
              <Building className="w-4 h-4 text-diu-green" />
              Corporate Advisory & Industrial Consultancies
            </h3>
            {teacher.consultancies && teacher.consultancies.length > 0 ? (
              <div className="space-y-3">
                {teacher.consultancies.map((cons) => (
                  <div key={cons.id} className="p-4 rounded-xl border border-indigo-100 bg-indigo-50/10 backdrop-blur-xs flex gap-3.5 items-start">
                    <div className="bg-indigo-50 text-indigo-600 p-2 rounded-lg shrink-0 border border-indigo-100 shadow-3xs">
                      <Building className="w-5 h-5" />
                    </div>
                    <div className="flex-1">
                      <span className="bg-indigo-100 text-indigo-800 text-[8px] font-sans font-bold uppercase px-1.5 py-0.5 rounded-xs">
                        Year: {cons.year}
                      </span>
                      <h4 className="text-xs font-bold text-slate-800 mt-1 leading-snug font-display">{cons.projectTitle}</h4>
                      <p className="text-[11px] text-slate-500 font-semibold mt-0.5">Organization: <span className="text-slate-700">{cons.organization}</span></p>
                      <p className="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-1">Role: {cons.role}</p>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-xs text-slate-400">No consulting or corporate advisory services submitted.</p>
            )}
          </div>
        )}

        {/* 11. Administrative Roles */}
        {activeTab === 'admin_roles' && (
          <div className="space-y-4">
            <h3 className="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
              <Shield className="w-4 h-4 text-diu-green" />
              University Administrative Roles
            </h3>
            <div className="p-5 bg-white/30 backdrop-blur-xs rounded-xl border border-white/60 flex items-start gap-4 ring-1 ring-slate-900/5">
              <div className="bg-diu-green/15 text-diu-green p-3 rounded-xl shrink-0">
                <Shield className="w-6 h-6" />
              </div>
              <div>
                <h4 className="text-sm font-bold text-slate-800 font-display">Active Administrative Designation</h4>
                <p className="text-xs font-semibold text-slate-600 mt-1">
                  Role: <span className="text-diu-orange font-bold font-sans uppercase">{hasAdminRole ? displayAdminRole : 'Regular Faculty Member'}</span>
                </p>
                <p className="text-xs text-slate-500 font-sans mt-2 leading-relaxed">
                  Responsible for organizing board syllabus evaluations, student counseling reviews, organizing local examinations, and monitoring quality assurance audits.
                </p>
              </div>
            </div>
          </div>
        )}

        {/* 12. Community Outreach */}
        {activeTab === 'community' && (
          <div className="space-y-4">
            <h3 className="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
              <Heart className="w-4 h-4 text-red-500" />
              Community Outreach & Student Advising
            </h3>
            {teacher.communityServices && teacher.communityServices.length > 0 ? (
              <div className="space-y-3">
                {teacher.communityServices.map((com) => (
                  <div key={com.id} className="p-4 rounded-xl border border-red-100 bg-red-50/10 backdrop-blur-xs flex gap-3.5 items-start">
                    <div className="bg-red-50 text-red-600 p-2 rounded-lg shrink-0 border border-red-100 shadow-3xs">
                      <Heart className="w-5 h-5" />
                    </div>
                    <div className="flex-1">
                      <span className="bg-red-100 text-red-800 text-[8px] font-sans font-bold uppercase px-1.5 py-0.5 rounded-xs">
                        {com.duration}
                      </span>
                      <h4 className="text-xs font-bold text-slate-800 mt-1 leading-snug font-display">{com.organization}</h4>
                      <p className="text-[11px] text-slate-500 font-semibold mt-0.5">Role: <span className="text-slate-700">{com.role}</span></p>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-xs text-slate-400">No community services or club supervisions listed.</p>
            )}
          </div>
        )}

      </div>
    </div>
  );
};
