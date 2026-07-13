import { useState, useEffect } from "react";
import { Link, useLocation, useParams } from "react-router-dom";
import { FaBars, FaTimes } from "react-icons/fa";
import { Search } from "lucide-react";
import Logo from "../../assets/DIU.png";
import { facultyMembers } from "../../data/facultyMembers";
import EmployeeSearchSystem from "../home/EmployeeSearchSystem";

const Header = () => {
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [isScrolled, setIsScrolled] = useState(false);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState("");
  const location = useLocation();
  const { deptId } = useParams();

  const toggleMenu = () => setIsMenuOpen(!isMenuOpen);
  const closeMenu = () => setIsMenuOpen(false);

  const handleCloseModal = () => {
    setIsModalOpen(false);
    setSearchTerm("");
  };

  useEffect(() => {
    const handleScroll = () => {
      setIsScrolled(window.scrollY > 10);
    };

    window.addEventListener("scroll", handleScroll);
    return () => window.removeEventListener("scroll", handleScroll);
  }, []);

  useEffect(() => {
    closeMenu();
  }, [location]);

  const getContactLink = () => {
    const pathSegments = location.pathname.split('/').filter(Boolean);
    
    // For department pages
    if (pathSegments[0] === 'department' && pathSegments[1] && !pathSegments[2]) {
      return `/department/${pathSegments[1]}/contact`;
    }
    
    // For faculty profile pages - get their department and show that department's contact
    if (pathSegments[0] === 'faculty' && pathSegments[1]) {
      const facultyMember = facultyMembers.find(f => f.id === pathSegments[1]);
      if (facultyMember?.department) {
        return `/department/${facultyMember.department}/contact`;
      }
    }
    
    // Default contact link
    return "https://daffodilvarsity.edu.bd/article/contact";
  };

  // Use Link or anchor tag based on the URL
  const ContactLink = () => {
    const contactUrl = getContactLink();
    const isExternal = contactUrl.startsWith('http');

    return isExternal ? (
      <a
        href={contactUrl}
        target="_blank"
        rel="noopener noreferrer"
        className={`font-semibold text-sm ${
          isScrolled ? "text-neutral-700" : "text-neutral-800"
        } hover:text-primary-600 transition-colors duration-200`}
      >
        Contact Us
      </a>
    ) : (
      <Link
        to={contactUrl}
        className={`font-semibold text-sm ${
          isScrolled ? "text-neutral-700" : "text-neutral-800"
        } hover:text-primary-600 transition-colors duration-200`}
      >
        Contact Us
      </Link>
    );
  };

  return (
    <header
      className={`fixed w-full z-30 transition-all duration-500 ${
        isScrolled ? "bg-white shadow-lg py-4" : "bg-white py-6 shadow-sm"
      }`}
    >
      <div className="container mx-auto px-4 flex justify-between items-center">
        <Link to="/" className="flex items-center space-x-3">
          <img src={Logo} alt="DIU Logo" className="w-40 object-contain" />
        </Link>

        {/* Desktop Navigation */}
        <nav className="hidden md:flex items-center space-x-10">
          <Link
            to="/"
            className={`font-semibold text-sm ${
              isScrolled ? "text-neutral-700" : "text-neutral-800"
            } hover:text-primary-600 transition-colors duration-200`}
          >
            Home
          </Link>
          <a
            href="https://forum.daffodilvarsity.edu.bd/"
            target="_blank"
            className={`font-semibold text-sm ${
              isScrolled ? "text-neutral-700" : "text-neutral-800"
            } hover:text-primary-600 transition-colors duration-200`}
          >
            Forum
          </a>
          <ContactLink />
          {/* Search Icon */}
          <button
            onClick={() => setIsModalOpen(true)}
            className={`p-2 rounded-full hover:bg-gray-100 transition-colors duration-200 ${
              isScrolled ? "text-neutral-700" : "text-neutral-800"
            }`}
          >
            <Search className="w-5 h-5" />
          </button>
          <a
            href="#"
            className="bg-gradient-to-r from-[#034EA2] to-[#011D3C] text-white px-6 py-2 rounded-lg font-medium shadow-lg shadow-blue-500/20 hover:shadow-xl hover:shadow-blue-500/30 transition-all duration-300"
          >
            Apply Now
          </a>
        </nav>

        {/* Mobile Menu Button */}
        <div className="md:hidden flex items-center space-x-4">
          <button
            onClick={() => setIsModalOpen(true)}
            className="text-neutral-800 hover:text-primary-600 focus:outline-none transition-colors duration-200"
          >
            <Search className="w-5 h-5" />
          </button>
          <button
            className="text-neutral-800 hover:text-primary-600 focus:outline-none transition-colors duration-200"
            onClick={toggleMenu}
            aria-label="Toggle menu"
          >
            {isMenuOpen ? <FaTimes size={24} /> : <FaBars size={24} />}
          </button>
        </div>
      </div>

      {/* Mobile Menu */}
      <div
        className={`md:hidden absolute w-full bg-white shadow-lg transition-all duration-300 ease-in-out ${
          isMenuOpen
            ? "opacity-100 translate-y-0"
            : "opacity-0 -translate-y-10 pointer-events-none"
        }`}
      >
        <nav className="container mx-auto px-4 py-4 flex flex-col space-y-4">
          <Link
            to="/"
            className="font-semibold text-sm text-neutral-800 py-2 hover:text-primary-600 transition-colors duration-200"
          >
            Home
          </Link>
          <a
            href="https://forum.daffodilvarsity.edu.bd/"
            target="_blank"
            className="font-semibold text-sm text-neutral-800 py-2 hover:text-primary-600 transition-colors duration-200"
          >
            Forum
          </a>
          <ContactLink />
          <a
            href="#"
            className="bg-primary-600 text-white font-semibold text-sm px-5 py-2.5 rounded-lg hover:bg-primary-700 transition-colors duration-200 text-center"
          >
            Apply Now
          </a>
        </nav>
      </div>

      {/* Search Modal */}
      <EmployeeSearchSystem
        isModalOpen={isModalOpen}
        onClose={handleCloseModal}
        searchTerm={searchTerm}
        onSearchChange={setSearchTerm}
      />
    </header>
  );
};

export default Header;
