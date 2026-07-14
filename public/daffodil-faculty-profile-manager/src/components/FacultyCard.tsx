import React from 'react';
import { Faculty, Department, Teacher } from '../types';
import { Award, Layers, Users, ChevronRight, UserCheck } from 'lucide-react';

interface FacultyCardProps {
  faculty: Faculty;
  departments: Department[];
  teachers: Teacher[];
  onClick: () => void;
}

export const FacultyCard: React.FC<FacultyCardProps> = ({
  faculty,
  departments,
  teachers,
  onClick
}) => {
  const facultyDepts = departments.filter(d => d.facultyId === faculty.id);
  const facultyTeachers = teachers.filter(t => 
    facultyDepts.some(d => d.id === t.departmentId)
  );

  // Find Dean details
  const dean = teachers.find(t => t.id === faculty.deanId);

  return (
    <div 
      className="bg-white/40 backdrop-blur-md rounded-2xl border border-white/60 shadow-sm hover:shadow-xl hover:border-white/80 transition-all duration-300 overflow-hidden group flex flex-col justify-between ring-1 ring-diu-green/10 hover:ring-diu-green/25"
      id={`faculty-card-${faculty.code}`}
    >
      {/* Visual Header image */}
      <div className="relative h-44 overflow-hidden bg-slate-100">
        <img 
          src={faculty.image} 
          alt={faculty.name} 
          className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
        />
        <div className="absolute inset-0 bg-linear-to-t from-black/80 via-black/40 to-transparent" />
        
        {/* Floating badge */}
        <div className="absolute top-4 right-4 bg-diu-orange text-white text-xs font-display font-extrabold px-2.5 py-1 rounded-md shadow-md tracking-wider">
          {faculty.code}
        </div>

        {/* Title inside image for elegant magazine feel */}
        <div className="absolute bottom-4 left-4 right-4">
          <h3 className="text-white font-display font-bold text-lg leading-snug drop-shadow-xs">
            {faculty.name}
          </h3>
        </div>
      </div>

      {/* Description */}
      <div className="p-5 flex-1 flex flex-col justify-between">
        <p className="text-xs text-slate-500 font-sans leading-relaxed mb-4 line-clamp-2">
          {faculty.description}
        </p>

        {/* Dean Information Block */}
        {dean && (
          <div className="mb-5 p-3.5 bg-white/40 backdrop-blur-xs rounded-xl border border-white/60 flex items-start gap-3 ring-1 ring-diu-green/5">
            <div className="w-10 h-10 rounded-lg overflow-hidden bg-slate-200 shrink-0 border border-diu-green/10">
              <img src={dean.avatar} alt={dean.name} className="w-full h-full object-cover" />
            </div>
            <div>
              <div className="flex items-center gap-1.5 text-[10px] text-diu-orange font-semibold tracking-wider uppercase">
                <UserCheck className="w-3 h-3" />
                Dean of Faculty
              </div>
              <p className="text-xs font-semibold text-slate-800 line-clamp-1">{dean.name}</p>
              <p className="text-[10px] text-slate-500 font-mono">{dean.email}</p>
            </div>
          </div>
        )}

        {/* Footer Metrics and Button */}
        <div className="pt-4 border-t border-white/40 flex items-center justify-between">
          <div className="flex gap-4">
            <div className="flex items-center gap-1 text-slate-600">
              <Layers className="w-3.5 h-3.5 text-diu-green" />
              <span className="text-xs font-medium">{facultyDepts.length} Depts</span>
            </div>
            <div className="flex items-center gap-1 text-slate-600">
              <Users className="w-3.5 h-3.5 text-diu-green" />
              <span className="text-xs font-medium">{facultyTeachers.length} Members</span>
            </div>
          </div>

          <button 
            onClick={onClick}
            className="text-xs font-semibold text-diu-green hover:text-diu-orange flex items-center gap-1 transition-colors group-hover:translate-x-1 duration-200 cursor-pointer"
          >
            Explore
            <ChevronRight className="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  );
};
