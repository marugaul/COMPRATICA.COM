import React from "react";
import { Link } from "react-router-dom";

export const Navbar = () => {
    return (
        <nav className="navbar navbar-expand-lg navbar-dark bg-dark">
            <div className="container-fluid">

                <span className="navbar-brand">
                    SwiftPay Demo
                </span>

                <button
                    className="navbar-toggler"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#navbarNav"
                >
                    <span className="navbar-toggler-icon"></span>
                </button>

                <div className="collapse navbar-collapse" id="navbarNav">

                    <ul className="navbar-nav">

                        <li className="nav-item">
                            <Link className="nav-link" to="/">
                                Home
                            </Link>
                        </li>

                        <li className="nav-item">
                            <Link className="nav-link" to="/auth">
                                Autorización
                            </Link>
                        </li>

                        <li className="nav-item">
                            <Link className="nav-link" to="/void">
                                Anulación
                            </Link>
                        </li>

                    </ul>

                </div>

            </div>
        </nav>
    );
};