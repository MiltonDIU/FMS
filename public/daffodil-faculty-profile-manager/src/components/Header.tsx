import React from 'react';
import { Search, Building, Users, BookOpen, Lock, Info } from 'lucide-react';

interface HeaderProps {
  onSearch: (query: string) => void;
  searchQuery: string;
  onLoginClick: () => void;
  stats: {
    facultiesCount: number;
    departmentsCount: number;
    teachersCount: number;
  };
}

export const Header: React.FC<HeaderProps> = ({
  onSearch,
  searchQuery,
  onLoginClick,
  stats
}) => {
  return (
    <header className="bg-white/75 backdrop-blur-md border-b border-white/40 shadow-xs sticky top-0 z-40" id="diu-header">
      {/* Top micro-bar with DIU Green/Blue colors */}
      <div className="bg-linear-to-r from-diu-green-dark to-diu-blue-dark text-white text-[11px] font-sans tracking-wide py-1.5 px-4 md:px-8 flex justify-between items-center">
        <div className="flex items-center gap-4">
          <span className="font-medium">Smart City campus: Ashulia, Savar, Dhaka</span>
          <span className="hidden sm:inline text-white/35">|</span>
          <span className="hidden sm:inline text-white/80">Email: info@daffodilvarsity.edu.bd</span>
        </div>
        <div className="flex items-center gap-3">
          <a href="https://daffodilvarsity.edu.bd" target="_blank" rel="noopener noreferrer" className="hover:text-diu-orange-light transition-colors flex items-center gap-1.5 font-semibold">
            Main Site <Info className="w-3 h-3" />
          </a>
        </div>
      </div>

      {/* Main header block */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          
          {/* Branded Logo and Title */}
          <div className="flex items-center gap-3 cursor-pointer select-none" onClick={() => window.location.reload()}>
            <div className="relative flex items-center justify-center w-11 h-11 bg-diu-green text-white font-display font-extrabold text-2xl rounded-xl shadow-md overflow-hidden border-2 border-diu-orange">
              <div className="absolute -top-1 -right-1 w-4 h-4 bg-diu-orange rotate-45" />
              D
            </div>
            <div>
              <div className="flex items-center gap-2">
                <span className="font-display font-black text-xl text-diu-green tracking-tight">DAFFODIL</span>
                <span className="bg-diu-blue text-white text-[9px] font-sans font-bold px-2 py-0.5 rounded-xs tracking-wider uppercase">DIRECTORY</span>
              </div>
              <p className="text-[11px] text-slate-500 font-sans font-semibold uppercase tracking-widest">International University, Bangladesh</p>
            </div>
          </div>

          {/* Search bar inside header */}
          <div className="relative flex-1 max-w-lg mx-0 md:mx-8">
            <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
              <Search className="w-4 h-4 text-diu-green" />
            </div>
            <input
              type="text"
              placeholder="Search faculty by name, department, research, keywords..."
              value={searchQuery}
              onChange={(e) => onSearch(e.target.value)}
              className="block w-full pl-10 pr-12 py-2.5 border border-slate-200 rounded-xl text-sm bg-white/50 backdrop-blur-xs hover:bg-white/90 focus:bg-white focus:outline-none focus:ring-2 focus:ring-diu-green focus:border-diu-green transition-all placeholder:text-slate-400 shadow-2xs"
            />
            {searchQuery && (
              <button 
                onClick={() => onSearch('')} 
                className="absolute inset-y-0 right-0 pr-3.5 flex items-center text-xs font-semibold text-slate-400 hover:text-slate-600 transition-colors"
              >
                Clear
              </button>
            )}
          </div>

          {/* Badge with count and Teacher Login Button */}
          <div className="flex items-center gap-3 justify-between md:justify-end">
            <div className="hidden lg:flex items-center gap-3 bg-slate-50 border border-slate-200/60 rounded-xl p-1.5 pl-3">
              <div className="text-right">
                <p className="text-[9px] text-slate-400 font-bold uppercase tracking-wider">Academic Portal</p>
                <p className="text-xs font-extrabold text-slate-700 font-sans">Active Directory</p>
              </div>
              <span className="bg-diu-green/10 text-diu-green font-mono font-bold text-xs px-3 py-1.5 rounded-lg border border-diu-green/10 shadow-3xs">
                {stats.teachersCount} Scholars
              </span>
            </div>

            <button
              onClick={onLoginClick}
              className="bg-diu-green hover:bg-diu-green-hover text-white text-xs font-bold px-4 py-2.5 rounded-xl transition-all duration-200 flex items-center gap-2 shadow-sm border border-diu-green/25 cursor-pointer active:scale-95"
            >
              <Lock className="w-3.5 h-3.5 text-diu-orange" />
              <span>Teacher Login</span>
            </button>
          </div>

        </div>
      </div>

      {/* Dynamic Statistics Bar using Daffodil Orange/Green combination */}
      <div className="bg-linear-to-r from-diu-blue to-diu-green text-white py-2.5 text-xs shadow-inner">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap justify-around md:justify-start gap-6 md:gap-12 font-medium">
          <div className="flex items-center gap-2">
            <Building className="w-4 h-4 text-diu-orange-light shrink-0" />
            <span className="font-sans"><strong>{stats.facultiesCount}</strong> Academic Faculties</span>
          </div>
          <div className="flex items-center gap-2">
            <Building className="w-4 h-4 text-diu-orange-light shrink-0" />
            <span className="font-sans"><strong>{stats.departmentsCount}</strong> Departments</span>
          </div>
          <div className="flex items-center gap-2">
            <Users className="w-4 h-4 text-diu-orange-light shrink-0" />
            <span className="font-sans"><strong>{stats.teachersCount}</strong> Faculty Profiles</span>
          </div>
        </div>
      </div>
    </header>
  );
};
