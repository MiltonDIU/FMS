import { useState, useEffect } from "react";
import { useParams, Link, useSearchParams } from "react-router-dom";
import RoleFilterSidebar from "../components/department/RoleFilterSidebar.jsx";
import FacultyCard from "../components/department/FacultyCard.jsx";
import {
  departmentalManagementRoles,
  departmentalFacultyMembersRoles,
} from "../data/facultyRoles";
import { departments } from "../data/department";
import { facultyMembers } from "../data/facultyMembers";

const DepartmentPage = () => {
  const { deptId } = useParams();

  const [searchParams, setSearchParams] = useSearchParams();
  const [selectedRole, setSelectedRole] = useState(
    searchParams.get("role") || ""
  );
  const [searchTerm, setSearchTerm] = useState(
    searchParams.get("search") || ""
  );
  const [department, setDepartment] = useState(null);
  const [facultyList, setFacultyList] = useState([]);
  const [filteredFaculty, setFilteredFaculty] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const dept = departments.find((d) => d.id === deptId);
    if (dept) {
      setDepartment(dept);
      const faculty = facultyMembers.filter((f) => f.department === deptId);
      setFacultyList(faculty);
      setLoading(false);
    } else {
      setLoading(false);
    }
  }, [deptId]);

  useEffect(() => {
    if (facultyList.length > 0) {
      const filtered = facultyList.filter((faculty) => {
        if (selectedRole === "all-management") {
          return departmentalManagementRoles.includes(faculty.role);
        }
        const matchesRole = selectedRole ? faculty.role === selectedRole : true;
        const matchesSearch = searchTerm
          ? faculty.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            faculty.email.toLowerCase().includes(searchTerm.toLowerCase())
          : true;
        return matchesRole && matchesSearch;
      });
      setFilteredFaculty(filtered);

      const params = {};
      if (selectedRole) params.role = selectedRole;
      if (searchTerm) params.search = searchTerm;
      setSearchParams(params, { replace: true });
    }
  }, [selectedRole, searchTerm, facultyList, setSearchParams]);

  const handleRoleChange = (role) => {
    setSelectedRole(role);
  };

  const handleSearch = (term) => {
    setSearchTerm(term);
  };

  const renderContent = () => {
    if (filteredFaculty.length === 0) {
      return (
        <div className="bg-white rounded-xl shadow-sm p-12 text-center">
          <p className="text-xl text-neutral-600 mb-4">
            No faculty members found.
          </p>
          <button
            onClick={() => {
              setSelectedRole("");
              setSearchTerm("");
            }}
            className="text-primary-600 hover:text-primary-700 font-medium"
          >
            Clear filters
          </button>
        </div>
      );
    }

    const managementMembers = filteredFaculty.filter((faculty) =>
      departmentalManagementRoles.includes(faculty.role)
    );

    const facultyMembers = filteredFaculty.filter(
      (faculty) => !departmentalManagementRoles.includes(faculty.role)
    );

    return (
      <div className="space-y-8">
        {/* Management Section - Only show if has items */}
        {managementMembers.length > 0 && (
          <div>
            <h2 className="text-2xl font-bold text-neutral-800 mb-4">
              Departmental Management
            </h2>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 gap-2 md:gap-4 w-full">
              {managementMembers.map((faculty) => (
                <FacultyCard key={faculty.id} faculty={faculty} />
              ))}
            </div>
          </div>
        )}

        {/* Divider - Only show if both sections have content */}
        {managementMembers.length > 0 && facultyMembers.length > 0 && (
          <div className="border-b border-gray-200 my-8"></div>
        )}

        {/* Faculty Members Section - Only show if has items */}
        {facultyMembers.length > 0 && (
          <div>
            <h2 className="text-2xl font-bold text-neutral-800 mb-4">
              Departmental Faculty Members
            </h2>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 gap-2 md:gap-4 w-full">
              {facultyMembers.map((faculty) => (
                <FacultyCard key={faculty.id} faculty={faculty} />
              ))}
            </div>
          </div>
        )}
      </div>
    );
  };

  if (loading) {
    return (
      <div className="container mx-auto py-20 min-h-screen">
        <div className="animate-pulse space-y-8">
          <div className="h-8 bg-neutral-200 rounded w-1/3"></div>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {[1, 2, 3, 4, 5, 6].map((i) => (
              <div key={i} className="bg-white rounded-xl shadow-sm p-4">
                <div className="aspect-[4/3] bg-neutral-200 rounded-lg mb-4"></div>
                <div className="space-y-3">
                  <div className="h-6 bg-neutral-200 rounded w-3/4"></div>
                  <div className="h-4 bg-neutral-200 rounded w-1/2"></div>
                  <div className="h-4 bg-neutral-200 rounded w-2/3"></div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  if (!department) {
    return (
      <div className="container mx-auto py-20 text-center min-h-screen">
        <h2 className="text-2xl font-bold mb-4">Department Not Found</h2>
        <p className="text-neutral-600 mb-6">
          The department you are looking for does not exist.
        </p>
        <Link to="/" className="btn-primary">
          Return to Home
        </Link>
      </div>
    );
  }

  return (
    <div className="pt-16 min-h-screen">
      {/* Breadcrumb */}
      <div className="relative bg-blue-900 text-white overflow-hidden py-16">
        <div className="absolute inset-0">
          <img
            src="/banner.png"
            alt="Campus Building"
            className="w-full h-full object-cover opacity-50"
          />
          <div className="absolute inset-0 bg-gradient-to-t from-diuBlue via-transparent to-transparent"></div>
        </div>

        <div className="relative z-10 container mx-auto px-4 py-16 h-full flex flex-col justify-center">
          <div className="flex items-center gap-2 mb-4 text-sm md:text-base text-gray-200">
            <Link
              to="/"
              className="hover:text-white transition-colors duration-200"
            >
              Home
            </Link>
            <span className="text-gray-300">/</span>
            <p className="text-gray-300">{department.facultyFullName}</p>
          </div>

          {/* Department Title */}
          <h1 className="text-4xl md:text-5xl font-extrabold text-white ">
            {department.name}
          </h1>

          {/* Optional Subtitle / Description */}
          <p className="mt-2 text-gray-200 md:text-lg max-w-3xl">
            Discover our distinguished faculty members of the {department.name}{" "}
            and explore their expertise, research interests, and achievements.
          </p>
        </div>
      </div>

      <div className="container mx-auto py-12 min-h-screen">
        <div className="flex flex-col md:flex-row gap-4 md:gap-10 px-4 md:px-0">
          <div className="w-full md:w-1/2 lg:w-1/3">
            <RoleFilterSidebar
              facultyRoles={departmentalFacultyMembersRoles}
              managementRoles={departmentalManagementRoles}
              selectedRole={selectedRole}
              onRoleChange={handleRoleChange}
            />
          </div>

          <div className="w-full md:w-1/2 lg:w-2/3">{renderContent()}</div>
        </div>
      </div>
    </div>
  );
};

export default DepartmentPage;
