'use client';

import { useState, useEffect, use } from "react";
import Link from "next/link";
import FacultyCard from "../../../src/components/department/FacultyCard.jsx";

const DEFAULT_AVATAR = "https://faculty.daffodilvarsity.edu.bd/images/teacher/dd39c67c90c18b9a102bc56d0a9119ca.JPG";

export default function DepartmentPage({ params }) {
  const resolvedParams = use(params);
  const { faculty, department: deptCode } = resolvedParams;

  const [deptDetails, setDeptDetails] = useState(null);
  const [teachers, setTeachers] = useState([]);
  const [adminRoles, setAdminRoles] = useState([]);
  const [designations, setDesignations] = useState([]);
  const [selectedFilter, setSelectedFilter] = useState({ type: "all" });
  const [searchTerm, setSearchTerm] = useState("");
  const [visibleFacultyCount, setVisibleFacultyCount] = useState(12);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetch(`/api/v1/departments/${deptCode.toLowerCase()}/directory`)
      .then(res => res.json())
      .then(data => {
        setDeptDetails({
          name: data.department.name,
          facultyFullName: data.department.faculty_name
        });

        const mappedTeachers = data.teachers.map(t => ({
          id: t.webpage || t.id.toString(),
          name: t.name,
          role: t.administrative_roles && t.administrative_roles.length > 0
            ? t.administrative_roles.map(r => r.name).join(', ')
            : (t.designation || "Faculty Member"),
          academicDesignation: t.designation || "Faculty Member",
          email: t.secondary_email || "",
          imageUrl: t.photo || DEFAULT_AVATAR,
          faculty: faculty,
          department: deptCode,
          administrative_roles: t.administrative_roles || []
        }));

        setTeachers(mappedTeachers);
        setAdminRoles(data.administrative_roles);
        setDesignations(data.designations);
        setLoading(false);
      })
      .catch(err => {
        console.error(err);
        setLoading(false);
      });
  }, [faculty, deptCode]);

  // Reset visible faculty count on filter change
  useEffect(() => {
    setVisibleFacultyCount(12);
  }, [selectedFilter]);

  // Search filter
  const searchedTeachers = teachers.filter(t => 
    searchTerm 
      ? (t.name.toLowerCase().includes(searchTerm.toLowerCase()) || 
         t.email.toLowerCase().includes(searchTerm.toLowerCase()))
      : true
  );

  // Group division based on filter
  let displayManagement = [];
  let displayFaculty = [];

  if (selectedFilter.type === "all") {
    displayManagement = searchedTeachers.filter(t => t.administrative_roles.length > 0);
    displayFaculty = searchedTeachers.filter(t => t.administrative_roles.length === 0);
  } else if (selectedFilter.type === "admin") {
    displayManagement = searchedTeachers.filter(t => 
      t.administrative_roles.some(r => r.id === selectedFilter.id)
    );
    displayFaculty = [];
  } else if (selectedFilter.type === "designation") {
    displayManagement = [];
    displayFaculty = searchedTeachers.filter(t => 
      t.academicDesignation.toLowerCase() === selectedFilter.name.toLowerCase()
    );
  }

  // Count helpers
  const getAdminRoleCount = (roleId) => {
    return teachers.filter(t => t.administrative_roles.some(r => r.id === roleId)).length;
  };

  const getDesignationCount = (desigName) => {
    return teachers.filter(t => t.academicDesignation.toLowerCase() === desigName.toLowerCase()).length;
  };

  // Totals for sidebar headers
  const totalManagementCount = teachers.filter(t => t.administrative_roles.length > 0).length;
  const totalFacultyCount = teachers.filter(t => t.administrative_roles.length === 0).length;

  if (loading) {
    return (
      <div className="container mx-auto py-20 text-center text-sm font-semibold text-gray-400">
        Loading Department Directory...
      </div>
    );
  }

  if (!deptDetails) {
    return (
      <div className="container mx-auto py-20 text-center">
        <h2 className="text-xl font-bold">Department Not Found</h2>
        <Link href="/" className="text-blue-600 hover:underline mt-4 inline-block">Back to Home</Link>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-slate-50">
      {/* Banner */}
      <div className="relative bg-gradient-to-r from-[#034EA2] to-[#011D3C] text-white overflow-hidden py-16">
        <div className="absolute inset-0 bg-gradient-to-t from-blue-950 via-transparent to-transparent opacity-60"></div>
        <div className="relative z-10 container mx-auto px-4 lg:px-12">
          <div className="flex items-center gap-2 mb-4 text-xs font-semibold text-gray-300">
            <Link href="/" className="hover:text-white transition">Home</Link>
            <span>/</span>
            <span className="uppercase">{faculty}</span>
            <span>/</span>
            <span className="text-white uppercase">{deptCode}</span>
          </div>
          <h1 className="text-3xl md:text-5xl font-extrabold text-white">{deptDetails.name}</h1>
          <p className="mt-2 text-gray-200 text-sm max-w-2xl">
            Explore our distinguished faculty members, administrative leadership, and academic designations.
          </p>
        </div>
      </div>

      <div className="container mx-auto py-12 px-4 lg:px-12">
        <div className="flex flex-col lg:flex-row gap-8">
          
          {/* Left Sidebar Menu */}
          <div className="w-full lg:w-1/4">
            <div className="bg-white rounded-3xl border border-gray-100 p-6 shadow-sm">
              <h3 className="text-xs font-extrabold uppercase tracking-wider text-gray-400 mb-6">
                Filter by Role
              </h3>
              
              <div className="space-y-6">
                {/* Option 1: All */}
                <button
                  onClick={() => setSelectedFilter({ type: "all" })}
                  className={`w-full flex justify-between items-center px-4 py-3 rounded-2xl text-sm font-bold transition duration-300 ${
                    selectedFilter.type === "all"
                      ? "bg-blue-50 text-[#034EA2]"
                      : "text-gray-600 hover:bg-slate-50"
                  }`}
                >
                  <span>All Management & Faculty Members</span>
                </button>

                {/* Section 2: Departmental Management */}
                {adminRoles.length > 0 && (
                  <div>
                    <h4 className="text-xs font-bold text-gray-400 uppercase tracking-wide px-4 mb-2 border-b border-gray-100 pb-2">
                      Departmental Management ({totalManagementCount})
                    </h4>
                    <div className="space-y-1">
                      {adminRoles.map((role) => (
                        <button
                          key={role.id}
                          onClick={() => setSelectedFilter({ type: "admin", id: role.id, name: role.name })}
                          className={`w-full flex justify-between items-center px-4 py-2.5 rounded-xl text-xs font-semibold transition duration-300 ${
                            selectedFilter.type === "admin" && selectedFilter.id === role.id
                              ? "bg-blue-50 text-[#034EA2] font-bold"
                              : "text-gray-600 hover:bg-slate-50"
                          }`}
                        >
                          <span>{role.name}</span>
                        </button>
                      ))}
                    </div>
                  </div>
                )}

                {/* Section 3: Departmental Faculty Members */}
                {designations.length > 0 && (
                  <div>
                    <h4 className="text-xs font-bold text-gray-400 uppercase tracking-wide px-4 mb-2 border-b border-gray-100 pb-2">
                      Departmental Faculty Members ({totalFacultyCount})
                    </h4>
                    <div className="space-y-1">
                      {designations.map((desig) => (
                        <button
                          key={desig.name}
                          onClick={() => setSelectedFilter({ type: "designation", name: desig.name })}
                          className={`w-full flex justify-between items-center px-4 py-2.5 rounded-xl text-xs font-semibold transition duration-300 ${
                            selectedFilter.type === "designation" && selectedFilter.name === desig.name
                              ? "bg-blue-50 text-[#034EA2] font-bold"
                              : "text-gray-600 hover:bg-slate-50"
                          }`}
                        >
                          <span>{desig.name}</span>
                        </button>
                      ))}
                    </div>
                  </div>
                )}

              </div>
            </div>
          </div>

          {/* Right Main Grid */}
          <div className="w-full lg:w-3/4 space-y-12">
            
            {/* Search Input bar */}
            <div className="bg-white rounded-3xl border border-gray-100 p-4 shadow-sm">
              <input
                type="text"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                placeholder="Search teachers by name or email..."
                className="w-full px-4 py-3 rounded-2xl bg-slate-55 border-none focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
              />
            </div>

            {/* Division 1: Management Section */}
            {displayManagement.length > 0 && (
              <div>
                <h2 className="text-xl font-bold text-gray-800 mb-6">
                  Departmental Management ({displayManagement.length})
                </h2>
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                  {displayManagement.map((t) => (
                    <FacultyCard key={t.id} faculty={t} />
                  ))}
                </div>
              </div>
            )}

            {/* Divider */}
            {displayManagement.length > 0 && displayFaculty.length > 0 && (
              <div className="border-t border-gray-200/60 my-8"></div>
            )}

            {/* Division 2: Faculty Members Section */}
            {displayFaculty.length > 0 && (
              <div className="space-y-8">
                <div>
                  <h2 className="text-xl font-bold text-gray-800 mb-6">
                    Departmental Faculty Members ({displayFaculty.length})
                  </h2>
                  <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    {displayFaculty.slice(0, visibleFacultyCount).map((t) => (
                      <FacultyCard key={t.id} faculty={t} />
                    ))}
                  </div>
                </div>

                {/* Show More Button */}
                {displayFaculty.length > visibleFacultyCount && (
                  <div className="text-center pt-4">
                    <button
                      onClick={() => setVisibleFacultyCount(prev => prev + 12)}
                      className="px-8 py-3 bg-white hover:bg-slate-50 border border-gray-200 text-blue-900 font-bold text-xs uppercase tracking-wider rounded-2xl transition duration-300 shadow-sm"
                    >
                      Show More
                    </button>
                  </div>
                )}
              </div>
            )}

            {displayManagement.length === 0 && displayFaculty.length === 0 && (
              <div className="bg-white rounded-3xl p-16 text-center border border-gray-100 shadow-sm">
                <p className="text-gray-400 font-medium">No faculty members found matching this filter.</p>
              </div>
            )}

          </div>

        </div>
      </div>
    </div>
  );
}
