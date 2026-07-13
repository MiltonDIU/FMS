import { useState } from "react";
import { Search } from "lucide-react";
import EmployeeSearchSystem from "./EmployeeSearchSystem";

const SearchBar = () => {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState("");

  const handleCloseModal = () => {
    setIsModalOpen(false);
    setSearchTerm("");
  };

  return (
    <>
      <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
        <div
          className="relative flex-grow cursor-pointer group"
          onClick={() => setIsModalOpen(true)}
        >
          <input
            type="text"
            placeholder="Search by name, Email, Phone, Designation..."
            value={searchTerm}
            readOnly
            className="w-full py-3 px-4 pl-12 border border-neutral-300 rounded-md cursor-pointer group-hover:border-blue-400 transition-colors"
          />
          <Search className="absolute left-4 top-1/2 transform -translate-y-1/2 text-neutral-400 w-5 h-5 group-hover:text-blue-500 transition-colors" />
        </div>
      </div>

      <EmployeeSearchSystem
        isModalOpen={isModalOpen}
        onClose={handleCloseModal}
        searchTerm={searchTerm}
        onSearchChange={setSearchTerm}
      />
    </>
  );
};

export default SearchBar;
