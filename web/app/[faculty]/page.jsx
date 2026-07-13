'use client';

import { use } from "react";
import Home from "../page";

export default function FacultyPage({ params }) {
  const resolvedParams = use(params);
  return <Home params={resolvedParams} />;
}
