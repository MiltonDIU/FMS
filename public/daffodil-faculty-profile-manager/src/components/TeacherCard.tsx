import React from 'react';
import { Teacher, Department } from '../types';
import { Mail, Phone, MapPin, Award, ArrowRight, UserCheck, GraduationCap, Globe } from 'lucide-react';

interface TeacherCardProps {
  teacher: Teacher;
  departmentName?: string;
  onClick: () => void;
}

export const TeacherCard: React.FC<TeacherCardProps> = ({
  teacher,
  departmentName,
  onClick
}) => {
  // Safely extract properties to support both existing mock format and real API formats
  const displayName = teacher.name || `${teacher.first_name || ''} ${teacher.last_name || ''}`.trim() || 'Scholar';
  const displayAvatar = teacher.avatar || teacher.photo || '';
  const displayOffice = teacher.office || teacher.office_room || 'N/A';
  const displayPhone = teacher.phone || teacher.personal_phone || 'N/A';
  const displayEmail = teacher.email || 'N/A';
  
  const displayAdminRole = teacher.administrativeRole || 
    (teacher.administrative_roles && teacher.administrative_roles.length > 0 
      ? (typeof teacher.administrative_roles[0] === 'string' 
          ? teacher.administrative_roles[0] 
          : teacher.administrative_roles[0].name)
      : '');
  const isAdministrative = !!displayAdminRole && displayAdminRole !== 'None';

  const interestsList = teacher.researchInterests || 
    (teacher.research_interest 
      ? teacher.research_interest.split(',').map(s => s.trim()).filter(Boolean) 
      : []);

  return (
    <div 
      className={`bg-white/40 backdrop-blur-md rounded-xl border transition-all duration-300 overflow-hidden group flex flex-col justify-between cursor-pointer ring-1 ${
        isAdministrative 
          ? 'border-diu-orange/30 hover:border-diu-orange/60 ring-diu-orange/10 hover:ring-diu-orange/25 shadow-sm' 
          : 'border-white/60 hover:border-white/95 ring-diu-green/10 hover:ring-diu-green/25 shadow-sm'
      }`}
      onClick={onClick}
      id={`teacher-card-${teacher.id}`}
    >
      <div>
        {/* Card Header Top Accent color */}
        <div className={`h-1.5 ${isAdministrative ? 'bg-diu-orange' : 'bg-diu-green'}`} />

        <div className="p-5">
          {/* Main info row */}
          <div className="flex items-start gap-4">
            {/* Avatar or customized fallback image */}
            <div className="relative w-16 h-16 shrink-0 rounded-full overflow-hidden border-2 border-white shadow-md">
              {displayAvatar ? (
                <img 
                  src={displayAvatar} 
                  alt={displayName} 
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                />
              ) : (
                <div className="w-full h-full bg-diu-green text-white flex items-center justify-center font-display font-bold text-lg">
                  {displayName.split(' ').slice(-1)[0]?.[0] || 'S'}
                </div>
              )}
            </div>

            {/* Basic Info */}
            <div className="flex-1 min-w-0">
              <div className="flex flex-wrap items-center gap-1.5 mb-1">
                {isAdministrative && (
                  <span className="inline-flex items-center gap-1 bg-diu-orange/15 text-diu-orange text-[9px] font-sans font-bold uppercase px-1.5 py-0.5 rounded-sm border border-diu-orange/25">
                    <UserCheck className="w-2.5 h-2.5" />
                    {displayAdminRole}
                  </span>
                )}
                <span className="text-slate-500 text-[10px] font-semibold uppercase tracking-wider">{teacher.designation}</span>
              </div>
              <h4 className="text-sm font-semibold text-slate-800 tracking-tight leading-snug line-clamp-1 group-hover:text-diu-green transition-colors">
                {displayName}
              </h4>
              <p className="text-[11px] text-slate-500 font-medium truncate mt-0.5">{departmentName}</p>
            </div>
          </div>

          {/* Core metadata/Details */}
          <div className="mt-5 space-y-2 border-t border-white/40 pt-3">
            <div className="flex items-center gap-2 text-xs text-slate-600">
              <Mail className="w-3.5 h-3.5 text-slate-400 shrink-0" />
              <span className="truncate hover:text-diu-green transition-colors font-mono">{displayEmail}</span>
            </div>
            <div className="flex items-center gap-2 text-xs text-slate-600">
              <Phone className="w-3.5 h-3.5 text-slate-400 shrink-0" />
              <span className="font-sans">{displayPhone}</span>
            </div>
            <div className="flex items-center gap-2 text-xs text-slate-600">
              <MapPin className="w-3.5 h-3.5 text-slate-400 shrink-0" />
              <span className="truncate font-sans leading-tight">{displayOffice}</span>
            </div>
          </div>

          {/* Research Interest Badge Row */}
          {interestsList.length > 0 && (
            <div className="mt-4 flex flex-wrap gap-1">
              {interestsList.slice(0, 2).map((interest, idx) => (
                <span 
                  key={idx} 
                  className="bg-white/40 text-slate-600 border border-white/60 text-[9px] font-sans px-2 py-0.5 rounded-sm"
                >
                  {interest}
                </span>
              ))}
              {interestsList.length > 2 && (
                <span className="bg-white/50 text-slate-400 text-[8px] font-sans font-bold px-1.5 py-0.5 rounded-sm border border-white/60">
                  +{interestsList.length - 2}
                </span>
              )}
            </div>
          )}
        </div>
      </div>

      {/* Footer view profile link */}
      <div className="px-5 py-3.5 bg-white/30 border-t border-white/40 flex items-center justify-between group-hover:bg-white/60 transition-colors">
        <div className="flex items-center gap-1.5">
          <GraduationCap className={`w-4 h-4 ${isAdministrative ? 'text-diu-orange' : 'text-diu-green'}`} />
          <span className="text-[10px] text-slate-400 font-semibold uppercase font-sans">
            {teacher.publications?.length || 0} Publications
          </span>
        </div>
        <span className="text-xs font-semibold text-diu-green group-hover:text-diu-orange flex items-center gap-1 transition-all">
          Profile
          <ArrowRight className="w-3 h-3 group-hover:translate-x-0.5 transition-transform" />
        </span>
      </div>
    </div>
  );
};
