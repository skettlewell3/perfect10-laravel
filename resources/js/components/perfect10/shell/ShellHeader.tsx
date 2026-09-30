import FlavourDropdown from './FlavourDropdown';

export default function ShellHeader() {
    return (
        <>
            <div className="headerLogo">
                <button
                    id="profileDropdownToggle"
                    type="button"
                    aria-label="Profile menu"
                    disabled
                >
                    <img
                        src="/assets/logos/FullLogo_Transparent_smallr.png"
                        alt="Perfect10"
                    />
                </button>
            </div>

            <div id="shellHeader">
                <div className="profileRow">
                    <div className="profileIdentity">
                        <div className="profileName">&nbsp;</div>
                        <div className="profilePoints">&nbsp;</div>
                    </div>

                    <FlavourDropdown />
                </div>

                <div className="profileTickerRow" />
            </div>
        </>
    );
}
