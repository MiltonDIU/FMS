import { Fragment, useState, useCallback } from "react";
import { Search, X, Mail, Phone, Briefcase } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { facultyMembers } from "../../data/facultyMembers";

const SearchModal = ({
  isOpen,
  onClose,
  searchTerm,
  onSearchChange,
  searchResults,
  isLoading,
}) => {
  const navigate = useNavigate();

  if (!isOpen) return null;

  const handleFacultyClick = (facultyId) => {
    onClose(); // Close the modal
    navigate(`/faculty/${facultyId}`); // Navigate to faculty profile
  };

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        {/* Backdrop */}
        <div
          className="fixed inset-0 transition-opacity bg-black bg-opacity-40 backdrop-blur-sm"
          onClick={onClose}
        />

        {/* Modal */}
        <div className="relative inline-block w-full max-w-5xl p-6 my-8 text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl">
          {/* Search Input */}
          <div className="relative mb-6">
            <input
              type="text"
              value={searchTerm}
              onChange={(e) => onSearchChange(e.target.value)}
              placeholder="Search by name, email, phone, designation..."
              className="w-full py-3 px-4 pl-12 pr-10 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              autoFocus
            />
            <div className="absolute left-4 top-1/2 -translate-y-1/2">
              {isLoading ? (
                <div className="w-5 h-5 border-2 border-gray-300 border-t-blue-500 rounded-full animate-spin" />
              ) : (
                <Search className="w-5 h-5 text-gray-400" />
              )}
            </div>
            <button
              onClick={onClose}
              className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          {/* Results */}
          <div className="max-h-[60vh] overflow-y-auto pr-3 pb-2">
            {!isLoading && searchResults?.length > 0 ? (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {searchResults?.map((result) => (
                  <div
                    key={result?.id}
                    onClick={() => handleFacultyClick(result.id)}
                    className="group flex items-start gap-4 p-4 rounded-lg hover:bg-neutral-100 transition-colors cursor-pointer border border-gray-200"
                  >
                    <img
                      src={result?.imageUrl}
                      alt={result?.name}
                      className="w-14 h-20 md:w-[74px] md:h-20 rounded-md object-cover"
                    />
                    <div>
                      <h3 className="font-semibold text-neutral-900 group-hover:text-blueText">
                        {result?.name}
                      </h3>
                      <p className="text-sm text-neutral-500">{result?.role}</p>
                      <div className="mt-1 text-sm text-neutral-600">
                        <p className="hover:text-blue-600">{result?.email}</p>
                        <p className="hover:text-blue-600">{result?.phone}</p>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            ) : searchTerm && !isLoading ? (
              <div className="text-center py-12">
                <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                  <Search className="w-8 h-8 text-gray-400" />
                </div>
                <p className="text-gray-500 text-lg">
                  No results found for "{searchTerm}"
                </p>
                <p className="text-gray-400 text-sm mt-2">
                  Try searching by name, email, phone, or designation
                </p>
              </div>
            ) : !searchTerm ? (
              <div className="text-center py-12">
                <div className="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4">
                  <Search className="w-8 h-8 text-blue-500" />
                </div>
                <p className="text-gray-600 text-lg">Start typing to search</p>
                <p className="text-gray-400 text-sm mt-2">
                  Search by name, email, phone, or designation
                </p>
              </div>
            ) : null}
          </div>
        </div>
      </div>
    </div>
  );
};

const EmployeeSearchSystem = ({
  isModalOpen,
  onClose,
  searchTerm,
  onSearchChange,
}) => {
  const [searchResults, setSearchResults] = useState([]);
  const [isLoading, setIsLoading] = useState(false);

  const performSearch = useCallback((value) => {
    if (!value || value.length < 2) {
      setSearchResults([]);
      setIsLoading(false);
      return;
    }

    setIsLoading(true);

    // Simulate loading for better UX
    setTimeout(() => {
      const searchLower = value.toLowerCase().trim();
      const results = facultyMembers.filter(
        (faculty) =>
          faculty.name?.toLowerCase().includes(searchLower) ||
          faculty.email?.toLowerCase().includes(searchLower) ||
          faculty.phone?.toLowerCase().includes(searchLower) ||
          faculty.designation?.toLowerCase().includes(searchLower) ||
          faculty.department?.toLowerCase().includes(searchLower)
      );

      setSearchResults(results);
      setIsLoading(false);
    }, 300);
  }, []);

  const handleSearchChange = (value) => {
    onSearchChange(value);
    performSearch(value);
  };

  return (
    <SearchModal
      isOpen={isModalOpen}
      onClose={onClose}
      searchTerm={searchTerm}
      onSearchChange={handleSearchChange}
      searchResults={searchResults}
      isLoading={isLoading}
    />
  );
};

export default EmployeeSearchSystem;
