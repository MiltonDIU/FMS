import { useState, useEffect } from "react";
import { useParams, Link } from "react-router-dom";
import { FaArrowLeft, FaPhone, FaEnvelope, FaUserTie } from "react-icons/fa";
import { departments } from "../data/department";

const DepartmentContactPage = () => {
  const { deptId } = useParams();
  const [contactData, setContactData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const department = departments.find((d) => d.id === deptId);

  useEffect(() => {
    const fetchContacts = async () => {
      try {
        const response = await fetch(
          `https://daffodilvarsity.edu.bd/api/v1/contacts/${deptId.toLowerCase()}`
        );
        if (!response.ok) throw new Error("Failed to fetch contacts");
        const data = await response.json();
        setContactData(data);
      } catch (err) {
        console.error("Error fetching contacts:", err);
        setError(err.message);
      } finally {
        setLoading(false);
      }
    };
    if (deptId) fetchContacts();
  }, [deptId]);

  // Loading Skeleton
  if (loading) {
    return (
      <div className="container mx-auto py-20 text-center">
        <div className="animate-pulse space-y-4">
          <div className="h-8 bg-neutral-200 rounded w-1/3 mx-auto"></div>
          <div className="h-4 bg-neutral-200 rounded w-1/4 mx-auto"></div>
          <div className="max-w-2xl mx-auto mt-8">
            {[1, 2, 3].map((i) => (
              <div key={i} className="bg-white rounded-lg p-6 mb-4 space-y-3">
                <div className="h-6 bg-neutral-200 rounded w-3/4"></div>
                <div className="h-4 bg-neutral-200 rounded w-1/2"></div>
                <div className="h-4 bg-neutral-200 rounded w-1/3"></div>
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  // Error State
  if (error) {
    return (
      <div className="container mx-auto py-20 text-center">
        <h2 className="text-2xl font-bold text-red-600 mb-4">Error</h2>
        <p className="text-neutral-600">{error}</p>
        <Link to={`/department/${deptId}`} className="btn-primary mt-6">
          Return to Department
        </Link>
      </div>
    );
  }

  // Reusable Card Component
  const ContactCard = ({ title, people }) => (
    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-all p-6">
      <h2 className="text-xl font-semibold text-neutral-800 mb-5 flex items-center gap-2">
        <FaUserTie className="text-blueText" /> <span className="text-blueText">{title}</span>
      </h2>
      <div className="space-y-5">
        {Object.values(people).map((person) => (
          <div
            key={person.id}
            className="bg-gray-50 hover:bg-gray-100 rounded-lg p-4 transition"
          >
            <h3 className="text-lg font-semibold text-gray-800">
              {person?.name}
            </h3>
            <p className="text-sm text-gray-600 mb-3">
              {person?.designation}
            </p>
            <div className="space-y-2 text-sm">
              {person?.email && (
                <div className="flex items-center gap-2">
                  <FaEnvelope className="text-diuBlue" />
                  <a
                    href={`mailto:${person.email}`}
                    className="text-gray-800 hover:text-diuBlue transition"
                  >
                    {person.email}
                  </a>
                </div>
              )}
              {person?.mobile && (
                <div className="flex items-center gap-2">
                  <FaPhone className="text-diuBlue" />
                  <p className="text-gray-800">{person.mobile}</p>
                </div>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  );

  return (
    <div className="pt-24 min-h-screen bg-gray-50">
      <div className="container mx-auto px-4">
        <Link
          to={`/department/${deptId}`}
          className="pt-10 px-5 inline-flex items-center text-neutral-600 mb-6 hover:text-brand-blue transition-colors"
        >
          <FaArrowLeft className="mr-2" />
          Back to {department?.name || "Department"}
        </Link>

        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-10 mb-10">
          <h1 className="text-3xl font-bold text-blueText mb-2">
            Contact Information
          </h1>
          <p className="text-neutral-600 mb-8">
            {contactData?.department?.department_name ||
              department?.name}{" "}
            — Contact Directory
          </p>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            {contactData?.deans &&
              Object.values(contactData.deans).length > 0 && (
                <ContactCard title="Dean’s Office" people={contactData?.deans} />
              )}

            {contactData?.department_heads &&
              Object.values(contactData?.department_heads).length > 0 && (
                <ContactCard
                  title="Department Head’s Office"
                  people={contactData?.department_heads}
                  
                />
              )}

            {contactData?.department_officers &&
              Object.values(contactData?.department_officers).length > 0 && (
                <ContactCard
                  title="Department Officers"
                  people={contactData?.department_officers}
                />
              )}

            {!contactData?.deans &&
              !contactData?.department_heads &&
              !contactData?.department_officers && (
                <div className="text-center col-span-full py-10">
                  <p className="text-gray-600 text-lg">
                    No contact information available.
                  </p>
                </div>
              )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default DepartmentContactPage;
