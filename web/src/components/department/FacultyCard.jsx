import { useState, useRef, useEffect } from "react";
import { Link } from "react-router-dom";
import { FaTimes, FaArrowRight } from "react-icons/fa";
import { ArrowRight } from "lucide-react";

const FacultyCard = ({ faculty }) => {
  const [showPopup, setShowPopup] = useState(false);
  const [popupPosition, setPopupPosition] = useState({
    horizontal: "right",
    offsetX: 0,
  });
  const popupRef = useRef(null);
  const cardRef = useRef(null);
  const timeoutRef = useRef(null);

  useEffect(() => {
    const updatePopupPosition = () => {
      if (cardRef.current && showPopup) {
        const cardRect = cardRef.current.getBoundingClientRect();
        const viewportWidth = window.innerWidth;
        const popupWidth = 320; // Base width of popup (w-80)
        const spaceOnRight = viewportWidth - cardRect.right;
        const spaceOnLeft = cardRect.left;

        let horizontal = "right";
        let offsetX = 0;
        if (spaceOnRight < popupWidth) {
          if (spaceOnLeft >= popupWidth) {
            horizontal = "left";
          } else {
            horizontal = spaceOnLeft > spaceOnRight ? "left" : "right";
            offsetX =
              horizontal === "right"
                ? -Math.max(0, popupWidth - spaceOnRight)
                : Math.max(0, popupWidth - spaceOnLeft);
          }
        }

        setPopupPosition({ horizontal, offsetX });
      }
    };

    updatePopupPosition();
    window.addEventListener("resize", updatePopupPosition);

    return () => {
      window.removeEventListener("resize", updatePopupPosition);
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
      }
    };
  }, [showPopup]);

  const handleMouseEnter = () => {
    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current);
    }
    setShowPopup(true);
  };

  const handleMouseLeave = (e) => {
    const popup = popupRef.current;
    const card = cardRef.current;

    if (popup && card) {
      const rect = popup.getBoundingClientRect();
      const isMovingToPopup =
        e.clientX >= rect.left &&
        e.clientX <= rect.right &&
        e.clientY >= rect.top &&
        e.clientY <= rect.bottom;

      if (isMovingToPopup) {
        return;
      }
    }

    timeoutRef.current = setTimeout(() => {
      setShowPopup(false);
    }, 100);
  };

  const handlePopupMouseEnter = () => {
    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current);
    }
  };

  const handlePopupMouseLeave = () => {
    timeoutRef.current = setTimeout(() => {
      setShowPopup(false);
    }, 100);
  };

  return (
    <div className="relative">
      {/* Faculty Card */}
      <Link to={`/faculty/${faculty?.id}`}>
        <div
          ref={cardRef}
          className="group bg-white rounded-xl shadow-sm hover:shadow-md border transition-all duration-300 overflow-hidden cursor-pointer"
          onMouseEnter={handleMouseEnter}
          onMouseLeave={handleMouseLeave}
          role="button"
          tabIndex={0}
          aria-expanded={showPopup}
          onKeyDown={(e) => {
            if (e.key === "Enter" || e.key === " ") {
              setShowPopup(!showPopup);
            }
            if (e.key === "Escape") {
              setShowPopup(false);
            }
          }}
        >
          <div className="flex flex-row p-3 gap-4 items-start ">
            <div className="w-1/3 flex-shrink-0">
              <div className="h-28 rounded-md overflow-hidden">
                <img
                  src={faculty?.imageUrl}
                  alt={faculty?.name}
                  className="w-full h-full object-cover"
                />
              </div>
            </div>
            <div className="w-full flex flex-col justify-between min-h-[7rem]">
              <div>
                <p className="text-md text-neutral-600 font-medium mt-1">
                  {faculty?.role}
                </p>
                <h3 className="font-bold text-lg text-blueText transition-all duration-300 line-clamp-2 leading-[1.4] group-hover:tracking-wide group-hover:mb-2">
                  {faculty?.name}
                </h3>
                
              </div>
              <Link
                to={`/faculty/${faculty?.id}`}
                className="text-sm text-neutral-600 hover:text-primary-600 group-hover:text-diuBlue transition-colors flex items-center justify-end gap-2 mr-2 "
                onClick={(e) => e.stopPropagation()}
              >
                View Profile
                <FaArrowRight className="text-sm text-neutral-500 group-hover:translate-x-1 group-hover:text-diuBlue transition-transform duration-200" />
              </Link>
            </div>
          </div>
        </div>
      </Link>

      {/* Popup */}
      {showPopup && (
        <div
          ref={popupRef}
          className={`absolute z-10 top-1/2 -translate-y-1/2 hidden md:flex ${
            popupPosition.horizontal === "right"
              ? "left-full ml-4"
              : "right-full mr-4"
          }`}
          style={{
            transform: `translate(${popupPosition.offsetX}px, -50%)`,
          }}
          onMouseEnter={handlePopupMouseEnter}
          onMouseLeave={handlePopupMouseLeave}
          role="dialog"
          aria-label={`Details for ${faculty?.name}`}
        >
          <div className="bg-white rounded-xl shadow-2xl p-6 w-full md:w-80 animate-fade-in">
            <button
              onClick={() => setShowPopup(false)}
              className="absolute top-2 right-2 text-neutral-400 hover:text-neutral-600 p-2 rounded-full hover:bg-neutral-100 transition-colors"
              aria-label="Close popup"
            >
              <FaTimes />
            </button>
            <div className="flex items-start space-x-4">
              <img
                src={faculty?.imageUrl}
                alt={faculty?.name}
                className="w-20 h-20 rounded-md object-cover"
              />
              <div className="pt-1">
                <h4 className="font-bold text-md text-blueText leading-tight">
                  {faculty?.name}
                </h4>
                <p className="text-neutral-700 text-sm">{faculty?.role}</p>
                <p className="text-neutral-700 text-sm">{faculty?.title}</p>
              </div>
            </div>
            <div className="py-1 border-b">
              <p>Personal Information</p>
            </div>
            <div className="mt-1 space-y-3">
              <div className="text-sm">
                <p className="font-medium text-neutral-900">Education:</p>
                <p className="text-neutral-800">
                  {faculty?.education[0]?.degree}
                </p>
                <p className="text-neutral-700 text-xs">
                  {faculty?.education[0]?.institution}
                </p>
              </div>
              <div className="text-sm">
                <div className="flex-wrap gap-1 mt-1">
                  <p className="font-medium">Contact:</p>
                  {/* <p className="flex items-center text-neutral-800">
                    Employee ID: {faculty.employeeId}
                  </p> */}
                  <p className="flex items-center text-neutral-800">
                    Cell-Phone:<strong>{faculty?.cellPhone}</strong>
                  </p>
                  <p className="flex items-center text-neutral-800">
                    E-mail: <strong> {faculty?.email}</strong>
                  </p>
                </div>
              </div>
            </div>
            <Link
              to={`/faculty/${faculty?.id}`}
              className="group mt-4 inline-block w-full text-center text-diuBlue border border-diuBlue hover:bg-gradient-to-r from-[#034EA2] to-[#011D3C] hover:text-white font-medium py-2 px-4 rounded-lg transition duration-300"
            >
              View Full Profile
              <ArrowRight className="inline-block ml-2 text-sm group-hover:translate-x-2 transition-transform duration-200" />
            </Link>
          </div>
        </div>
      )}
    </div>
  );
};

export default FacultyCard;
