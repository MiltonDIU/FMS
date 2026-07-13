import { useState, useEffect } from "react";

import { useParams, Link } from "react-router-dom";

import { FaArrowLeft } from "react-icons/fa";

import ProfileHeader from "../components/faculty/ProfileHeader";

import ProfileTabs from "../components/faculty/ProfileTabs";

import { facultyMembers } from "../data/facultyMembers";
import { departments } from "../data/department";

const FacultyProfilePage = () => {
  const { facultyId } = useParams();

  const [faculty, setFaculty] = useState(null);

  const [loading, setLoading] = useState(true);
  // console.log("facultyMembersf", facultyMembers);

  useEffect(() => {
    // Find the faculty member

    const facultyMember = facultyMembers.find((f) => f.id == facultyId);

    const department = departments.find(
      (d) => d.id == facultyMember.department
    );
    console.log("facultyMemberdsad", facultyMember);

    console.log("facultyMember with Department", facultyMember);

    if (facultyMember) {
      facultyMember.departmentData = department;
      setFaculty(facultyMember);
    }

    setLoading(false);
  }, [facultyId]);

  if (loading) {
    return (
      <div className="container mx-auto py-20 text-center px-4 sm:px-6 lg:px-8 md:gap-12">
        <div className="animate-pulse">
          <div className="h-40 bg-primary-200 rounded-lg mb-8"></div>

          <div className="h-32 w-32 bg-neutral-200 rounded-full mx-auto -mt-20 mb-6"></div>

          <div className="h-8 bg-neutral-200 rounded w-1/4 mx-auto mb-4"></div>

          <div className="h-4 bg-neutral-200 rounded w-2/4 mx-auto mb-8"></div>

          <div className="max-w-3xl mx-auto">
            <div className="h-10 bg-neutral-200 rounded mb-6"></div>

            <div className="space-y-3">
              {[1, 2, 3, 4].map((i) => (
                <div key={i} className="h-4 bg-neutral-200 rounded"></div>
              ))}
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (!faculty.id) {
    return (
      <div className="container mx-auto py-20 text-center px-4 sm:px-6 lg:px-8 md:gap-12">
        <h2 className="text-2xl font-bold mb-4">Faculty Member Not Found</h2>

        <p className="text-neutral-600 mb-6">
          The faculty member you are looking for does not exist.
        </p>

        <Link to="/" className="btn-primary">
          Return to Home
        </Link>
      </div>
    );
  }

  return (
    <div className="pt-24">
      <div className="container mx-auto px-4 pt-5 lg:px-12 bg-white rounded-xl sm:px-6 md:gap-12">
        <Link
          to={`/department/${faculty?.departmentData?.id}`}
          className="inline-flex items-center text-neutral-400 mb-6 hover:text-blueText transition-colors"
        >
          <FaArrowLeft className="mr-2" />
          Back to {faculty?.departmentData?.name}
        </Link>
      </div>

      <ProfileHeader faculty={faculty} />

      <div className="container mx-auto py-8 px-4 sm:px-6 lg:px-8 md:gap-12">
        <ProfileTabs faculty={faculty} />
      </div>
    </div>
  );
};

export default FacultyProfilePage;
